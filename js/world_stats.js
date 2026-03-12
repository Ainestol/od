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

RB: ${c.raid_kills ?? 0}<br>
Last: ${c.last_raid_name ?? "-"}<br>
Time: ${formatDateTime(c.last_raid_kill)}<br><br>

Epic: ${c.epic_kills ?? 0}<br>
Last: ${c.last_epic_name ?? "-"}<br>
Time: ${formatDateTime(c.last_epic_kill)}

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


/* =========================
   INITIAL LOAD
========================= */

loadWorldStats();


/* =========================
   AUTO REFRESH
========================= */

/* každou minutu */

setInterval(loadWorldStats,15000);