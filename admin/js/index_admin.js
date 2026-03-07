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
 document.getElementById("statVip24").innerText = d.vip_24h;
 document.getElementById("statVipOther").innerText = d.vip_other;

}

document.addEventListener("DOMContentLoaded", loadServerStats);