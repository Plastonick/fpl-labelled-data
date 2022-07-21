<?php

use Plastonick\FPL\Data\Context;

require_once __DIR__ . '/vendor/autoload.php';

$connection = new \PDO('pgsql:host=database;port=5432;dbname=fantasy-db', 'fantasy-user', 'fantasy-pwd');

ini_set('memory_limit', -1);

$type = $argv[1] ?? null;

$context = new Context($connection);

switch ($type) {
    case 'training':
        $training = generateTrainingData($connection, $context);
        if (count($training) === 0) {
            die("Could not retrieve any training data\n");
        }

        $resource = fopen(__DIR__ . '/dataset.csv', 'w+');

        fputcsv($resource, array_keys($training[0]));
        foreach ($training as $sample) {
            fputcsv($resource, $sample);
        }

        fclose($resource);

        echo "Generated training data\n";
        break;

    case 'unknown':
        $unknowns = generateUnknownData($connection, $context);
        if (count($unknowns) === 0) {
            die("Could not retrieve any unknown data\n");
        }

        $resource = fopen(__DIR__ . '/dataset.csv', 'w+');

        fputcsv($resource, array_keys($unknowns[0]));
        foreach ($unknowns as $unknown) {
            fputcsv($resource, $unknown);
        }

        fclose($resource);

        echo "Generated unknown data\n";
        break;

    default:
        die("Please specify the generation flavour: training, unknown\n");
}

function generateUnknownData(PDO $connection, Context $context): array
{
    $sql = <<<SQL
SELECT p.player_id, f.fixture_id
FROM fixtures f
         INNER JOIN players p ON (f.away_team_id = p.last_team_id OR f.home_team_id = p.last_team_id)
         INNER JOIN teams t ON p.last_team_id = t.team_id
         INNER JOIN player_season_positions psp ON p.player_id = psp.player_id
WHERE f.finished_provisional = FALSE
  AND f.finished = FALSE;
SQL;

    $statement = $connection->prepare($sql);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    $results = $statement->fetchAll();

    $data = [];
    $total = count($results);
    foreach ($results as $i => $result) {
        $data[] = $context->provide($result['player_id'], $result['fixture_id']);

        printProgressBar($i + 1, $total);
    }

    return $data;
}

function generateTrainingData(PDO $connection, Context $context): array
{
    $sql = <<<SQL
SELECT
    pp.player_id,
    pp.fixture_id,
    pp.kickoff_time,
    pp.total_points,
    ( SELECT AVG(total_points) FROM (SELECT pp2.total_points FROM player_performances pp2 WHERE pp2.player_id = pp.player_id AND pp2.kickoff_time < pp.kickoff_time ORDER BY kickoff_time DESC LIMIT 3) AS last_three) AS average_score,
    CASE WHEN pp.was_home THEN 1 ELSE 0 END AS was_home,
    COALESCE(f.team_h_difficulty, 0) AS home_difficulty,
    COALESCE(f.team_a_difficulty, 0) AS away_difficulty,
    pp.minutes,
    psp.position_id
FROM player_performances pp
         INNER JOIN fixtures f ON pp.fixture_id = f.fixture_id
         INNER JOIN player_season_positions psp ON (f.season_id = psp.season_id AND pp.player_id = psp.player_id)
ORDER BY pp.player_id, pp.kickoff_time DESC;
SQL;

    $statement = $connection->prepare($sql);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    $results = $statement->fetchAll();

    $data = [];
    $total = count($results);
    foreach ($results as $i => $result) {
        $datum = $context->provide($result['player_id'], $result['fixture_id']);
        $datum['score'] = $result['total_points'];

        $data[] = $datum;

        printProgressBar($i + 1, $total);
    }

    return $data;
}

function printProgressBar(int $done, int $total, string $info = '', int $width = 50)
{
    $percentageComplete = round(($done * 100) / $total);
    $bar = (int) round(($width * $percentageComplete) / 100);

    echo sprintf("[%s>%s] %s%% %s\r", str_repeat('=', $bar), str_repeat(' ', ($width - $bar)), $percentageComplete, $info);
}


