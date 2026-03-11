<?php

require_once __DIR__ . '/../config/db_game.php';

header('Content-Type: application/json; charset=utf-8');

try {

$castles = [
1 => "Gludio",
2 => "Dion",
3 => "Giran",
4 => "Oren",
5 => "Aden",
6 => "Innadril",
7 => "Goddard",
8 => "Rune",
9 => "Schuttgart"
];

/* ============================= */
/* TOP LEVEL */
/* ============================= */

$topLevel = $pdoGame->query("
SELECT char_name, level
FROM characters
WHERE accesslevel = 0
ORDER BY level DESC
LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);


/* ============================= */
/* TOP PVP */
/* ============================= */

$topPvP = $pdoGame->query("
SELECT char_name, pvpkills
FROM characters
WHERE accesslevel = 0
ORDER BY pvpkills DESC
LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);


/* ============================= */
/* TOP PK */
/* ============================= */

$topPK = $pdoGame->query("
SELECT char_name, pkkills
FROM characters
WHERE accesslevel = 0
ORDER BY pkkills DESC
LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);


/* ============================= */
/* TOP PLAYTIME */
/* ============================= */

$topTime = $pdoGame->query("
SELECT char_name, onlinetime
FROM characters
WHERE accesslevel = 0
ORDER BY onlinetime DESC
LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);


/* ============================= */
/* TOP ADENA */
/* ============================= */

$topAdena = $pdoGame->query("
SELECT 
c.char_name,
i.count AS adena
FROM items i
JOIN characters c ON c.charId = i.owner_id
WHERE i.item_id = 57
AND c.accesslevel = 0
ORDER BY i.count DESC
LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);


/* ============================= */
/* TOP CLANS */
/* ============================= */

$clans = $pdoGame->query("
SELECT 
cd.clan_id,
cd.clan_name,
cd.clan_level,
cd.reputation_score,
cd.hasCastle,
cd.crest_id,
leader.char_name AS leader_name,
COUNT(c.charId) AS members

FROM clan_data cd

LEFT JOIN characters c
ON c.clanid = cd.clan_id

LEFT JOIN characters leader
ON leader.charId = cd.leader_id

GROUP BY cd.clan_id, leader.char_name
ORDER BY cd.clan_level DESC, cd.reputation_score DESC
LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);


/* castle name */

foreach($clans as &$clan){
$clan['castle'] = $castles[$clan['hasCastle']] ?? "None";
}


echo json_encode([
"ok" => true,
"data" => [
"top_level" => $topLevel,
"top_pvp" => $topPvP,
"top_pk" => $topPK,
"top_time" => $topTime,
"top_adena" => $topAdena,
"top_clans" => $clans
]
]);

} catch(Exception $e){

echo json_encode([
"ok" => false,
"error" => $e->getMessage()
]);

}