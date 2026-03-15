/* =========================
   WORLD STATS LOADER
========================= */

async function loadWorldStats(){

try{

const res = await fetch('/api/world_stats.php');
const data = await res.json();

if(!data.ok) return;

const d = data.data;

renderList("topLevel", d.top_level, "level");
renderList("topPvP", d.top_pvp, "pvpkills");
renderList("topPK", d.top_pk, "pkkills");
renderList("topTime", d.top_time, "onlinetime");
renderList("topAdena", d.top_adena, "adena");

renderClans(d.top_clans);

}catch(e){
console.error("World stats error:", e);
}

}


/* =========================
   PLAYTIME FORMAT
========================= */

function formatPlaytime(seconds){

const h = Math.floor(seconds / 3600);
const m = Math.floor((seconds % 3600) / 60);
const s = seconds % 60;

if(h > 0) return `${h}h ${m}m`;
if(m > 0) return `${m}m ${s}s`;

return `${s}s`;
}


/* =========================
   PLAYER LIST RENDER
========================= */

function renderList(id, list, field){

const box = document.getElementById(id);
if(!box) return;

box.innerHTML = "";

list.forEach((p,i)=>{

const div = document.createElement("div");

div.className = "rank-row";

let value = p[field];

/* převod playtime */
if(field === "onlinetime"){
value = formatPlaytime(value);
}

div.innerHTML = `
<span class="rank">#${i+1}</span>
<span class="name">${p.char_name}</span>
<span class="value">${value}</span>
`;

box.appendChild(div);

});

}
function formatDateTime(sqlTime){

if(!sqlTime) return "-";

const d = new Date(sqlTime);

const hours = String(d.getHours()).padStart(2,"0");
const minutes = String(d.getMinutes()).padStart(2,"0");

const day = d.getDate();
const month = d.getMonth()+1;
const year = d.getFullYear();

return `${hours}:${minutes} ${day}.${month}.${year}`;

}

/* =========================
   CLAN LIST RENDER
========================= */

function renderClans(list){

const box = document.getElementById("topClans");

if(!box) return;

box.innerHTML="";

list.forEach((c,i)=>{

const div = document.createElement("div");
div.className="clan-row";

/* crest výběr */

let crest;

if(i === 0){
crest = "/img/clan1.png";
}
else if(i === 1){
crest = "/img/clan2.png";
}
else if(i === 2){
crest = "/img/clan3.png";
}
else{
crest = "/img/clan_default.png";
}
let rankClass = "";

if(i === 0) rankClass = "clan-rank-1";
else if(i === 1) rankClass = "clan-rank-2";
else if(i === 2) rankClass = "clan-rank-3";
div.innerHTML = `

<div class="clan-header">
<span class="rank">#${i+1}</span>
<span class="clan-title">${c.clan_name}</span>
</div>

<div class="clan-body">

<img class="clan-crest ${rankClass}" src="${crest}">

<div class="clan-info">

Leader: ${c.leader_name}<br>
Level ${c.clan_level} • Rep ${c.reputation_score}<br>
Members ${c.members}<br>
Castle: ${c.castle}<br><br>

<span class="raid">
RB: ${c.raid_kills ?? 0}<br>
Last: ${c.last_raid_name ?? "-"}<br>
Time: ${formatDateTime(c.last_raid_kill)}
</span><br><br>

<span class="epic">
Epic: ${c.epic_kills ?? 0}<br>
Last: ${c.last_epic_name ?? "-"}<br>
Time: ${formatDateTime(c.last_epic_kill)}
</span>
</div>
</div>
`;

box.appendChild(div);

});

}


/* =========================
   TAB SWITCHER
========================= */

document.querySelectorAll(".tab-btn").forEach(btn => {

btn.addEventListener("click", () => {

document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));

btn.classList.add("active");

const tab = btn.dataset.tab;

document.getElementById("tab-" + tab).classList.add("active");

});

});

document.querySelectorAll(".subtab-btn").forEach(btn => {

btn.addEventListener("click", () => {

document.querySelectorAll(".subtab-btn").forEach(b => b.classList.remove("active"));
document.querySelectorAll(".subtab-content").forEach(c => c.classList.remove("active"));

btn.classList.add("active");

const tab = btn.dataset.subtab;

document.getElementById("subtab-" + tab).classList.add("active");

});

});

/*=========================
    časy
==========================*/

function formatCountdown(diff){

let d = Math.floor(diff / 86400);
let h = Math.floor((diff % 86400) / 3600);
let m = Math.floor((diff % 3600) / 60);
let s = diff % 60;

let timerClass = "";

/* posledních 10 minut */

if(diff <= 600){
timerClass = "warning";
}

/* posledních 60 sekund */

if(diff <= 60){
timerClass = "danger";
}

return `
<span class="timer ${timerClass}">
<span class="timer-days">D ${String(d).padStart(2,"0")}</span>
<span class="timer-hours">H ${String(h).padStart(2,"0")}</span>
<span class="timer-minutes">M ${String(m).padStart(2,"0")}</span>
<span class="timer-seconds">S ${String(s).padStart(2,"0")}</span>
</span>
`;

}
/*=========================
    SJEDNOCENÉ FUNKCE
==========================*/

async function loadBoss(type){

const res = await fetch('/api/boss_tracker.php');
const json = await res.json();

if(!json.ok || !json.data) return;

const box = document.getElementById(type === "RAID" ? "raidBossList" : "grandBossList");
box.innerHTML = "";

const now = Math.floor(Date.now()/1000);

json.data.forEach(b => {
console.log(
b.boss_name,
"kill:", Number(b.kill_time),
"delay:", Number(b.respawn_delay),
"random:", Number(b.respawn_random),
"spawn:", Number(b.spawn_time)
);
if((b.boss_type || "").toLowerCase() !== type.toLowerCase()) return;

const killTime = Number(b.kill_time) || 0;
const delay = Number(b.respawn_delay) || 0;
const random = Number(b.respawn_random) || 0;
const spawnTime = Number(b.spawn_time) || 0;

const windowStart = killTime + delay;
const windowEnd = windowStart + random;

let status = "";
let statusClass = "";
let info = "";

/* boss je živý (spawnul po posledním killu) */

if(spawnTime > killTime){

    status = "ALIVE";
    statusClass = "alive";
    info = "Boss is alive";

}

/* boss byl zabit */

else if(killTime > 0){

    if(now < windowStart){

        status = "DEAD";
        statusClass = "dead";

        const diff = windowStart - now;
        info = `Spawn window in ${formatCountdown(diff)}`;

    }

    else if(now >= windowStart && now <= windowEnd){

        status = "RESPAWN WINDOW";
        statusClass = "window";

        const start = new Date(windowStart*1000).toLocaleString("cs-CZ");
        const end = new Date(windowEnd*1000).toLocaleString("cs-CZ");

        info = `Spawn window: ${start} – ${end}`;

    }

    else{

        status = "ALIVE";
        statusClass = "alive";
        info = "Boss should be alive";

    }

}

/* boss nikdy nebyl zabit */

else{

    status = "ALIVE";
    statusClass = "alive";
    info = "No kill recorded";

}

const div = document.createElement("div");

div.className = "raid-row";

div.innerHTML = `
<span class="raid-name">${b.boss_name}</span>
<span class="raid-level">Lv ${b.level ?? "?"}</span>
<span class="raid-status ${statusClass}">${status}</span>
<div class="raid-extra">${info}</div>
`;

box.appendChild(div);

});

}
/* =========================
   INITIAL LOAD
========================= */

loadWorldStats();
loadBoss("RAID");
loadBoss("GRAND");

/* =========================
   AUTO REFRESH
========================= */

setInterval(loadWorldStats,60000);
setInterval(()=>{
loadBoss("RAID");
loadBoss("GRAND");
},1000);

