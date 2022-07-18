<?php

require_once __DIR__ . '/vendor/autoload.php';

use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;


$connection = new \PDO('pgsql:host=database;port=5432;dbname=fantasy-db', 'fantasy-user', 'fantasy-pwd');


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

ini_set('memory_limit', -1);


$context = new \Plastonick\FPL\Data\Context($connection);

$statement = $connection->prepare($sql);
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
$results = $statement->fetchAll();
//$recordsByPlayer = [];
//
//foreach ($results as $result) {
//    $recordsByPlayer[$result['player_id']][] = $result;
//}

file_put_contents('data.json', json_encode($results));

$samples = [];
$targets = [];
$total = count($results);
foreach ($results as $i => $result) {
    $samples[] = $context->provide($result['player_id'], $result['fixture_id']);

    printProgressBar($i + 1, $total);

//    $samples[] = [
////        $result['kickoff_time'],
//        $result['average_score'],
//        $result['was_home'],
//        $result['home_difficulty'],
//        $result['away_difficulty'],
//        $result['position_id'],
//    ];

    $targets[] = $result['total_points'];
}

//$samples = [[60], [61], [62], [63], [65]];
//$targets = [3.1, 3.6, 3.8, 4, 4.1];

$trainingSamples = array_slice($samples, 0, 1000);
$trainingTargets = array_slice($targets, 0, 1000);

//$regression = new SVR(Kernel::SIGMOID);
$regression = new \Phpml\Regression\LeastSquares();
$regression->train($trainingSamples, $trainingTargets);

$predictions = $regression->predict(array_slice($samples, 1001, 10));
$actuals = array_slice($targets, 1001, 10);


foreach ($predictions as $i => $prediction) {
    $ind = $i + 1001;

    $prediction = round($prediction, 2);
    echo "Predicted {$prediction}.\tActually {$actuals[$i]}.\tAvg: {$samples[$ind][0]}\n";
}





function printProgressBar(int $done, int $total, string $info = '', int $width = 50)
{
    $percentageComplete = round(($done * 100) / $total);
    $bar = (int) round(($width * $percentageComplete) / 100);

    echo sprintf("[%s>%s] %s%% %s\r", str_repeat('=', $bar), str_repeat(' ', ($width - $bar)), $percentageComplete, $info);
}


