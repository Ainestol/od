async function loadServerStats(){

 const res = await fetch('/admin/api/server_stats.php',{
  credentials:'same-origin'
 });

 const data = await res.json();

 if(!data.ok) return;

 const d = data.data;

 document.getElementById("statWebUsers").innerText = d.web_users;
 document.getElementById("statGameAccounts").innerText = d.game_accounts;
 document.getElementById("statCharacters").innerText = d.characters;
 document.getElementById("statOnline").innerText = d.online;
 document.getElementById("statVip1").innerText = d.vip_1;
document.getElementById("statVip2").innerText = d.vip_2;
document.getElementById("statVip3").innerText = d.vip_3;
document.getElementById("statVoteTotal").innerText = d.vote_total;
document.getElementById("statDcTotal").innerText = d.dc_total;

}

document.addEventListener("DOMContentLoaded", loadServerStats);
setInterval(loadServerStats, 5000);