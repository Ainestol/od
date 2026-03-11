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

div.innerHTML = `
<div class="rank">#${i+1}</div>

<div class="clan-name">
<img class="clan-crest" src="/api/crest.php?id=${c.crest_id}">
${c.clan_name}
</div>

<div class="clan-info">
Leader: ${c.leader_name}<br>
Level ${c.clan_level} • Members ${c.members}<br>
Castle: ${c.castle}
</div>
`;

box.appendChild(div);

/* důležité */
setTimeout(()=>{

const container = document.getElementById(`crest-${c.crest_id}`);

if(c.crest_id){
loadCrest(c.crest_id, container);
}

},0);

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

setInterval(loadWorldStats,5000);