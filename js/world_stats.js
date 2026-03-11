function decodeDXT1(data, width, height){

const out = new Uint8ClampedArray(width*height*4);

let offset = 0;

for(let by=0; by<height/4; by++){
for(let bx=0; bx<width/4; bx++){

const c0 = data[offset] | (data[offset+1]<<8);
const c1 = data[offset+2] | (data[offset+3]<<8);
const bits = data[offset+4] | (data[offset+5]<<8) | (data[offset+6]<<16) | (data[offset+7]<<24);

offset += 8;

const r0=((c0>>11)&31)*255/31;
const g0=((c0>>5)&63)*255/63;
const b0=(c0&31)*255/31;

const r1=((c1>>11)&31)*255/31;
const g1=((c1>>5)&63)*255/63;
const b1=(c1&31)*255/31;

const colors=[
[r0,g0,b0,255],
[r1,g1,b1,255],
[(2*r0+r1)/3,(2*g0+g1)/3,(2*b0+b1)/3,255],
[(r0+2*r1)/3,(g0+2*g1)/3,(b0+2*b1)/3,255]
];

for(let py=0; py<4; py++){
for(let px=0; px<4; px++){

const idx=(bits>>(2*((3-py)*4+px)))&3;
const color=colors[idx];

const x=bx*4+px;
const y=by*4+py;

const i=(y*width+x)*4;

out[i]=color[0];
out[i+1]=color[1];
out[i+2]=color[2];
out[i+3]=255;

}
}

}
}

return out;
}

async function loadCrest(id, element){
if(!element) return;

const res = await fetch(`/api/crest.php?id=${id}`);
const buffer = await res.arrayBuffer();

const data = new Uint8Array(buffer);

const width=16;
const height=12;

const pixels = decodeDXT1(data,width,height);

const canvas=document.createElement("canvas");
canvas.width=width;
canvas.height=height;

const ctx=canvas.getContext("2d");
const img=ctx.createImageData(width,height);

img.data.set(pixels);

ctx.putImageData(img,0,0);

canvas.style.width="40px";
canvas.style.height="30px";
canvas.style.imageRendering="pixelated";

element.appendChild(canvas);
}
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
<span class="crest" id="crest-${c.crest_id}"></span>
${c.clan_name}
</div>

<div class="clan-info">
Leader: ${c.leader_name}<br>
Level ${c.clan_level} • Members ${c.members}<br>
Castle: ${c.castle}
</div>
`;

box.appendChild(div);

if(c.crest_id){

const container = document.getElementById(`crest-${c.crest_id}`);

loadCrest(c.crest_id, container);

}

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