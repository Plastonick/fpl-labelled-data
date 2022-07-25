<?php

namespace Plastonick\FPL\Data;

use PDO;

class Context
{
    public function __construct(private PDO $connection)
    {
    }

    public function provide(int $playerId, int $fixtureId): array
    {
        // TODO give a better indication to how good the opponent teams are

        $context = [
            'id' => $playerId . '-' . $fixtureId,
        ];

        $context = array_merge($context, $this->getFixtureData($playerId, $fixtureId));

        $results = $this->fetchRecentPerformance($playerId, $fixtureId);
        for ($i = 0; $i < 10; $i += 1) {
            $context["total_points_sub_{$i}"] = $results[$i]['total_points'] ?? 0;
            $context["minutes_sub_{$i}"] = $results[$i]['minutes'] ?? 0;
            $context["was_home_sub_{$i}"] = $results[$i]['was_home'] ?? -1;
            $context["home_difficulty_sub_{$i}"] = $results[$i]['home_difficulty'] ?? 0;
            $context["away_difficulty_sub_{$i}"] = $results[$i]['away_difficulty'] ?? 0;
            $context["position_id_sub_{$i}"] = $results[$i]['position_id'] ?? 0;
        }

        $results = $this->fetchHistoricPerformances($playerId, $fixtureId);
        for ($i = 0; $i < 10; $i += 1) {
            $context["historic_points_sub_{$i}"] = $results[$i]['total_points'] ?? -1;
            $context["historic_minutes_sub_{$i}"] = $results[$i]['total_minutes'] ?? -1;
            $context["historic_games_sub_{$i}"] = $results[$i]['games_played'] ?? -1;
        }

        return $context;
    }

    private function fetchRecentPerformance(int $playerId, int $fixtureId): array
    {
        $sql = <<<SQL
SELECT pp.total_points,
       pp.fixture_id,
       pp.minutes,
       pp.kickoff_time,
       CASE WHEN pp.was_home THEN 1 ELSE 0 END AS was_home,
       COALESCE(f.team_h_difficulty, 0)        AS home_difficulty,
       COALESCE(f.team_a_difficulty, 0)        AS away_difficulty,
       psp.position_id
FROM player_performances pp
         INNER JOIN fixtures f ON pp.fixture_id = f.fixture_id
         INNER JOIN player_season_positions psp ON (f.season_id = psp.season_id AND pp.player_id = psp.player_id)
WHERE pp.player_id = :playerId
  AND pp.kickoff_time < ( SELECT kickoff_time FROM fixtures WHERE fixture_id = :fixtureId )
ORDER BY pp.kickoff_time DESC
LIMIT 10;
SQL;


        $statement = $this->connection->prepare($sql);
        $statement->bindParam('playerId', $playerId);
        $statement->bindParam('fixtureId', $fixtureId);

        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        return $statement->fetchAll();
    }

    /**
     * Returns the per-season performance of the player up to that fixture. Includes the partial-season performance if
     * fixture is mid-season.
     *
     * @param int $playerId
     * @param int $fixtureId
     *
     * @return array
     */
    private function fetchHistoricPerformances(int $playerId, int $fixtureId): array
    {
        $sql = <<<SQL
SELECT SUM(pp.total_points)                             AS total_points,
       SUM(pp.minutes)                                  AS total_minutes, 
       SUM(CASE WHEN pp.minutes > 0 THEN 1 ELSE 0 END)  AS games_played
FROM player_performances pp
         INNER JOIN fixtures f ON pp.fixture_id = f.fixture_id
         INNER JOIN seasons s ON (f.season_id = s.season_id)
WHERE pp.player_id = :playerId
  AND f.kickoff_time < ( SELECT kickoff_time FROM fixtures WHERE fixture_id = :fixtureId )
GROUP BY s.season_id
ORDER BY s.season_id DESC
LIMIT 10;
SQL;

        $statement = $this->connection->prepare($sql);
        $statement->bindParam('playerId', $playerId);
        $statement->bindParam('fixtureId', $fixtureId);

        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        return $statement->fetchAll();
    }

    private function getFixtureData(int $playerId, int $fixtureId): array
    {
        static $fixtureData = null;
        static $playerData = null;

        if (!$fixtureData) {
            $fixtureData = $this->buildFixtureData();
        }

        if (!$playerData) {
            $playerData = $this->buildPlayerData();
        }

        $fixtureDatum = $fixtureData[$fixtureId];
        $playerDatum = $playerData[$playerId];

        return [
            'start_hour' => $fixtureDatum['start_hour'],
            'is_home' => (int) $fixtureDatum['home_team_id'] === (int) $playerDatum,
            'team_h_difficulty' => $fixtureDatum['team_h_difficulty'],
            'team_a_difficulty' => $fixtureDatum['team_a_difficulty'],
        ];
    }

    private function buildFixtureData(): array
    {
        $sql = <<<SQL
SELECT fixture_id, 
       TO_CHAR(kickoff_time, 'HH24') AS start_hour, 
       home_team_id, 
       team_h_difficulty, 
       team_a_difficulty
FROM fixtures;
SQL;

        $statement = $this->connection->prepare($sql);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $results = $statement->fetchAll();

        $data = [];
        foreach ($results as $result) {
            $data[$result['fixture_id']] = [
                'start_hour' => $result['start_hour'],
                'home_team_id' => $result['home_team_id'],
                'team_h_difficulty' => $result['team_h_difficulty'],
                'team_a_difficulty' => $result['team_a_difficulty'],
            ];
        }

        return $data;
    }

    private function buildPlayerData(): array
    {
        $sql = <<<SQL
SELECT player_id, last_team_id
FROM players;
SQL;

        $statement = $this->connection->prepare($sql);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $results = $statement->fetchAll();

        $data = [];
        foreach ($results as $result) {
            $data[$result['player_id']] = $result['last_team_id'];
        }

        return $data;
    }
}
