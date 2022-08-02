<?php

require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$log = new Logger('name');
$options = getopt('v');
$verbose = isset($options['v']);
$log->pushHandler(new StreamHandler('php://stdout', $verbose ? Logger::DEBUG : Logger::NOTICE));

$connection = new \PDO('pgsql:host=database;port=5432;dbname=fantasy-db', 'fantasy-user', 'fantasy-pwd');
$predictionResource = fopen(__DIR__ . '/predictions.csv', 'r');

$predictions = [];
while ($line = fgetcsv($predictionResource)) {
    if ($line[0] === 'id') {
        continue;
    }

    [$id, $prediction] = $line;
    [$playerId, $fixtureId] = explode('-', $id);


    $playerData = getPlayerData($connection, $playerId, $fixtureId);
    [$webName, $teamId, $positionId, $cost] = $playerData;

    if ($positionId <= 0) {
        // invalid
        continue;
    }

    $fixtureData = getFixtureData($connection, $fixtureId);
    [$homeTeamId, $awayTeamId, $homeTeam, $awayTeam, $kickoffTime, $gameWeek] = $fixtureData;

    $homeStatus = $teamId === $homeTeamId ? 'at home' : 'away';
    $opponent = $teamId === $homeTeamId ? $awayTeam : $homeTeam;

    $roundedPred = round($prediction, 2);
    $log->debug("{$webName} predicted to score {$roundedPred} {$homeStatus} against {$opponent}\n");

    if (!isset($predictions[$playerId])) {
        $predictions[$playerId] = [
            'id' => (int) $playerId,
            'name' => $webName,
            'position' => $positionId,
            'team' => $teamId,
            'predictions' => []
        ];
    }

    $predictions[$playerId]['predictions'][] = [
        'week' => (int) $gameWeek,
        'score' => (float) $prediction,
        'chanceOfPlaying' => 1,
        'cost' => $cost
    ];
}

file_put_contents('predictions.json', json_encode(array_values($predictions)));
$log->notice('Saved predictions to predictions.json');

function getFixtureData(PDO $connection, string $fixtureId): ?array
{
    static $cache = [];

    if (!array_key_exists($fixtureId, $cache)) {
        $sql = <<<SQL
SELECT home_team_id, away_team_id, home.name, away.name, kickoff_time, COALESCE(gw.event, -1)
FROM fixtures f 
    LEFT JOIN game_weeks gw ON f.game_week_id = gw.game_week_id 
    INNER JOIN teams away ON f.away_team_id = away.team_id
    INNER JOIN teams home ON f.home_team_id = home.team_id
WHERE fixture_id = :fixtureId
SQL;

        $statement = $connection->prepare($sql);
        $statement->execute(['fixtureId' => $fixtureId]);
        $statement->setFetchMode(PDO::FETCH_NUM);
        $data = $statement->fetchAll();
        $cache[$fixtureId] = $data[0] ?? [0, 0, 'na', 'na', 'na', 0];
    }

    return $cache[$fixtureId];
}

/**
 * @param PDO $connection
 * @param string $playerId
 * @param string $fixtureId
 *
 * @return array
 */
function getPlayerData(PDO $connection, string $playerId, string $fixtureId): array
{
    static $cache = [];

    if (!array_key_exists($playerId, $cache)) {
        $sql = <<<SQL
SELECT web_name, 
       last_team_id, 
       COALESCE(psp.position_id, -1),
       ( SELECT pp.value FROM player_performances pp WHERE pp.player_id = p.player_id ORDER BY id DESC LIMIT 1 )
FROM players p
         LEFT JOIN player_season_positions psp ON (p.player_id = psp.player_id AND psp.season_id = ( SELECT season_id FROM fixtures f WHERE f.fixture_id = :fixtureId ))
WHERE p.player_id = :playerId
LIMIT 1
SQL;

        $statement = $connection->prepare($sql);
        $statement->execute(['playerId' => $playerId, 'fixtureId' => $fixtureId]);
        $statement->setFetchMode(PDO::FETCH_NUM);
        $data = $statement->fetchAll();
        $cache[$playerId] = $data[0] ?? ['na', 0, 0, 0];
    }

    return $cache[$playerId];
}
