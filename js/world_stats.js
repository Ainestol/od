async function loadWorldStats(){

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

}

/* PLAYER LIST */

function renderList(id, list, field){

const box = document.getElementById(id);
if(!box) return;

box.innerHTML = "";

list.forEach((p,i)=>{

const div = document.createElement("div");

div.className = "rank-row";

div.innerHTML = `
<span class="rank">#${i+1}</span>
<span class="name">${p.char_name}</span>
<span class="value">${p[field]}</span>
`;

box.appendChild(div);

});

}


/* CLAN LIST */

function renderClans(list){

const box = document.getElementById("topClans");

if(!box) return;

box.innerHTML="";

list.forEach((c,i)=>{

const div = document.createElement("div");

div.className="clan-row";

div.innerHTML=`
<div class="rank">#${i+1}</div>
<div class="clan-name">${c.clan_name}</div>
<div class="clan-info">
Leader: ${c.leader_name}<br>
Level ${c.clan_level} • ${c.members} members • Castle: ${c.castle}
</div>
`;

box.appendChild(div);

});

}

loadWorldStats();

/* refresh každou minutu */

setInterval(loadWorldStats,60000);