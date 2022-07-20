<?php

require_once __DIR__ . '/vendor/autoload.php';

$connection = new \PDO('pgsql:host=database;port=5432;dbname=fantasy-db', 'fantasy-user', 'fantasy-pwd');

ini_set('memory_limit', -1);

if (file_exists(__DIR__ . '/samples.json')) {
    $samples = json_decode(file_get_contents(__DIR__ . '/samples.json'), true);
    $targets = json_decode(file_get_contents(__DIR__ . '/targets.json'), true);
} else {
    [$samples, $targets] = getSamples($connection);
}

if (!file_exists(__DIR__ . '/dataset.csv')) {
    if (count($samples) === 0) {
        die("No samples\n");
    }

    $resource = fopen(__DIR__ . '/dataset.csv', 'w+');
    fputcsv($resource, array_merge(array_keys($samples[0]), ['score']));

    foreach ($samples as $i => $sample) {
        fputcsv($resource, array_merge($sample, [$targets[$i]]));
    }

    echo "finished and output dataset.csv\n";
}

function getSamples(PDO $connection): array
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

    $context = new \Plastonick\FPL\Data\Context($connection);

    $statement = $connection->prepare($sql);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    $results = $statement->fetchAll();

    file_put_contents('data.json', json_encode($results));

    $samples = [];
    $targets = [];
    $total = count($results);
    foreach ($results as $i => $result) {
        $samples[] = $context->provide($result['player_id'], $result['fixture_id']);

        printProgressBar($i + 1, $total);

        $targets[] = $result['total_points'];
    }


    file_put_contents(__DIR__ . '/samples.json', json_encode($samples));
    file_put_contents(__DIR__ . '/targets.json', json_encode($targets));

    return [$samples, $targets];
}

function printProgressBar(int $done, int $total, string $info = '', int $width = 50)
{
    $percentageComplete = round(($done * 100) / $total);
    $bar = (int) round(($width * $percentageComplete) / 100);

    echo sprintf("[%s>%s] %s%% %s\r", str_repeat('=', $bar), str_repeat(' ', ($width - $bar)), $percentageComplete, $info);
}


