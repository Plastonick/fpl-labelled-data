<?php

require_once __DIR__ . '/vendor/autoload.php';

use Plastonick\FPL\Data\Context;

$connection = new \PDO('pgsql:host=database;port=5432;dbname=fantasy-db', 'fantasy-user', 'fantasy-pwd');

ini_set('memory_limit', -1);

$context = new Context($connection);

$unknowns = generateUnknownData($connection, $context);
if (count($unknowns) === 0) {
    die("Could not retrieve any unknown data\n");
}

$resource = fopen(__DIR__ . '/unknown.csv', 'w+');

fputcsv($resource, array_keys($unknowns[0]));
foreach ($unknowns as $unknown) {
    fputcsv($resource, $unknown);
}

fclose($resource);

echo "Generated unknown data\n";

function generateUnknownData(PDO $connection, Context $context): array
{
    $sql = <<<SQL
SELECT p.player_id, f.fixture_id
FROM fixtures f
         INNER JOIN players p ON (f.away_team_id = p.last_team_id OR f.home_team_id = p.last_team_id)
         INNER JOIN teams t ON p.last_team_id = t.team_id
WHERE f.season_id = 16
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

function printProgressBar(int $done, int $total, string $info = '', int $width = 50)
{
    $percentageComplete = round(($done * 100) / $total);
    $bar = (int) round(($width * $percentageComplete) / 100);

    echo sprintf("[%s>%s] %s%% %s\r", str_repeat('=', $bar), str_repeat(' ', ($width - $bar)), $percentageComplete, $info);
}


