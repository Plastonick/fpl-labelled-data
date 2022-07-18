<?php

namespace Plastonick\FPL\Data;

use PDO;

class Context
{
    public function __construct(private PDO $pdo)
    {
    }

    public function provide(int $playerId, int $fixtureId): array
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


        $statement = $this->pdo->prepare($sql);
        $statement->bindParam('playerId', $playerId);
        $statement->bindParam('fixtureId', $fixtureId);

        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $results = $statement->fetchAll();

        $context = [];
        for ($i = 0; $i < 10; $i += 1) {
            $context[] = [
                $results[$i]['total_points'] ?? 0,
                $results[$i]['minutes'] ?? 0,
                $results[$i]['was_home'] ?? 0,
                $results[$i]['home_difficulty'] ?? 0,
                $results[$i]['away_difficulty'] ?? 0,
                $results[$i]['position_id'] ?? 0,
            ];
        }

        return $context;
    }
}
