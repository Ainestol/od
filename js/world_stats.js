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

/* =========================
   RAID BOSS LOADER
========================= */

async function loadRaidBoss(){

const res = await fetch('/api/raidboss_status.php');
const json = await res.json();

if(!json.ok || !json.data) return;

const box = document.getElementById("raidBossList");
box.innerHTML = "";

const now = Math.floor(Date.now()/1000);

json.data.forEach(b=>{

let status = "ALIVE";
let statusClass = "alive";
let info = "";

/* hodnoty z API */

let windowEnd = parseInt(b.respawn_time) || 0;
let random = parseInt(b.respawn_random) || 0;

let windowStart = windowEnd - random;
let spawnTime = windowStart;

if(windowEnd > 0){

    if(now < spawnTime){

        status = "DEAD";
        statusClass = "dead";

        let diff = spawnTime - now;

        let h = Math.floor(diff/3600);
        let m = Math.floor((diff%3600)/60);
        let s = diff%60;

        info = `Spawn window in ${h}h ${m}m ${s}s`;

    }
    else if(now >= windowStart && now <= windowEnd){

        status = "RESPAWN WINDOW";
        statusClass = "window";

       let startDate = new Date(windowStart*1000);
       let endDate = new Date(windowEnd*1000);

       let start =
       `${startDate.getDate()}.${startDate.getMonth()+1} ${startDate.getHours().toString().padStart(2,"0")}:${startDate.getMinutes().toString().padStart(2,"0")}`;

       let end =
       `${endDate.getDate()}.${endDate.getMonth()+1} ${endDate.getHours().toString().padStart(2,"0")}:${endDate.getMinutes().toString().padStart(2,"0")}`;

       info = `Spawn window: ${start} – ${end}`;

    }

}

const div = document.createElement("div");
div.className = "raid-row";

div.innerHTML = `
<span class="raid-name">${b.name ?? "Unknown Boss"}</span>
<span class="raid-level">Lv ${b.level ?? "?"}</span>
<span class="raid-status ${statusClass}">${status}</span>
<div class="raid-extra" data-window="${spawnTime}">${info}</div>
`;

box.appendChild(div);

});

}
/* =========================
   GRAND BOSS LOADER
========================= */
async function loadGrandBoss(){

const res = await fetch('/api/grandboss_status.php');
const json = await res.json();

if(!json.ok || !json.data) return;

const box = document.getElementById("grandBossList");

box.innerHTML = "";

const now = Math.floor(Date.now()/1000);

json.data.forEach(b=>{

let status = "ALIVE";
let statusClass = "alive";
let info = "";

let respawnTime = Math.floor(b.respawn_time / 1000);

let windowStart = respawnTime + b.respawn;
let windowEnd = windowStart + b.respawn_random;

if(respawnTime > 0){

    if(now < windowStart){

        status = "DEAD";
        statusClass = "dead";

        let diff = windowStart - now;

        let h = Math.floor(diff/3600);
        let m = Math.floor((diff%3600)/60);
        let s = diff%60;

        info = `Spawn window in ${h}h ${m}m ${s}s`;

    }
    else if(now >= windowStart && now <= windowEnd){

        status = "RESPAWN WINDOW";
        statusClass = "window";

        let start = new Date(windowStart*1000)
        .toLocaleTimeString("cs-CZ",{hour:'2-digit',minute:'2-digit'});

        let end = new Date(windowEnd*1000)
        .toLocaleTimeString("cs-CZ",{hour:'2-digit',minute:'2-digit'});

        info = `Spawn window: ${start} – ${end}`;

    }

}

const div = document.createElement("div");

div.className = "raid-row";

div.innerHTML = `
<span class="raid-name">${b.name ?? "Unknown Boss"}</span>
<span class="raid-level">Lv ${b.level ?? "?"}</span>
<span class="raid-status ${statusClass}">${status}</span>
<div class="raid-extra" data-window="${windowStart}">${info}</div>
`;

box.appendChild(div);

});

}
/* =========================
   INITIAL LOAD
========================= */

loadWorldStats();
loadRaidBoss();
loadGrandBoss();

/* =========================
   AUTO REFRESH
========================= */

setInterval(loadWorldStats,60000);
setInterval(loadRaidBoss,60000);
setInterval(loadGrandBoss,60000);

/* =========================
   LIVE RAID COUNTDOWN
========================= */

setInterval(()=>{

const now = Math.floor(Date.now()/1000);

document.querySelectorAll("#raidBossList .raid-extra[data-window]").forEach(el=>{

let start = parseInt(el.dataset.window) || 0;

if(start > now){

let diff = start - now;

let h = Math.floor(diff/3600);
let m = Math.floor((diff%3600)/60);
let s = diff%60;

el.textContent = `Spawn window in ${h}h ${m}m ${s}s`;

}

});

},1000);
/* =========================
   GRAND BOSS LIVE COUNTDOWN
========================= */

setInterval(()=>{

const now = Math.floor(Date.now()/1000);

document.querySelectorAll("#grandBossList .raid-extra[data-window]").forEach(el=>{

let start = parseInt(el.dataset.window);

if(start > now){

let diff = start - now;

let h = Math.floor(diff/3600);
let m = Math.floor((diff%3600)/60);
let s = diff%60;

el.textContent = `Spawn window in ${h}h ${m}m ${s}s`;

}

});

},1000);