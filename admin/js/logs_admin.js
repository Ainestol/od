async function loadLogs() {

 const res = await fetch('/admin/api/logs_list.php?limit=1000', {
   credentials: 'same-origin'
 });

 const data = await res.json();

 if (!data.ok) {
   console.error("Logs error:", data);
   return;
 }

 const rows = data.logs.map(log => {

   let meta = "";

   try {
     meta = JSON.stringify(
       typeof log.meta === "string" ? JSON.parse(log.meta) : log.meta,
       null,
       2
     );
   } catch(e) {
     meta = log.meta;
   }

   return [
     log.created_at,
     log.action,
     log.user_id ?? "-",
     log.target_id ?? "-",
     log.status,
     meta
   ];

 });

 const table = $('#logsTable').DataTable({

   destroy: true,
   data: rows,

   columns: [
     { title: "Time" },
     { title: "Action" },
     { title: "User" },
     { title: "Target" },
     { title: "Status" },
     { title: "Meta" }
   ],

   pageLength: 50,
   order: [[0, "desc"]],

   initComplete: function () {

     const api = this.api();

     api.columns().every(function () {

       const column = this;

       const select = $('<select><option value="">All</option></select>')
         .appendTo($(column.header()))
      .on('change', function () {

 const val = $(this).val();

 column
  .search(val, true, false)
  .draw();

});

     });

     updateFilters(api);

     api.on('draw', function () {
       updateFilters(api);
     });

   }

 });

}

function updateFilters(api) {

 api.columns().every(function () {

   const column = this;
   const select = $('select', column.header());

   const current = select.val();

   select.empty().append('<option value="">All</option>');

   const data = api
     .rows({ search: 'applied' })
     .data()
     .toArray();

   const colIndex = column.index();

   const unique = [...new Set(data.map(row => row[colIndex]))];

   unique.sort().forEach(val => {
     select.append(`<option value="${val}">${val}</option>`);
   });

   if (select.find(`option[value="${current}"]`).length) {
  select.val(current);
} else {
  select.val("");
}

 });

}
async function loadSecurity(){

 const res = await fetch('/admin/api/security_stats.php?days='+securityDays,{
  credentials:'same-origin'
 });

 const data = await res.json();

 if(!data.ok) return;

 const d = data.data;


 /* BRUTE FORCE */

 const brute = document.getElementById("securityBrute");
 if(brute){
  brute.innerHTML="";
  d.brute_ips.forEach(r=>{
   const tr=document.createElement("tr");
   tr.innerHTML=`<td>${r.ip}</td><td>${r.fails}</td>`;
   brute.appendChild(tr);
  });
 }


 /* RECENT FAILS */

 const recent = document.getElementById("securityRecent");
 if(recent){
  recent.innerHTML="";
  d.recent_fails.forEach(r=>{
   const tr=document.createElement("tr");
   tr.innerHTML=`<td>${r.ip}</td><td>${r.fails}</td>`;
   recent.appendChild(tr);
  });
 }


 /* RATE LIMIT */

 const rate = document.getElementById("securityRate");
 if(rate){
  rate.innerHTML="";
  d.rate_limits.forEach(r=>{
   const tr=document.createElement("tr");
   tr.innerHTML=`<td>${r.ip}</td><td>${r.blocks}</td>`;
   rate.appendChild(tr);
  });
 }


 /* ATTACKED ACCOUNTS */

 const acc = document.getElementById("securityAccounts");
 if(acc){
  acc.innerHTML="";
  d.accounts.forEach(r=>{
   const tr=document.createElement("tr");
   tr.innerHTML=`<td>${r.email}</td><td>${r.fails}</td>`;
   acc.appendChild(tr);
  });
 }


 /* ECONOMY */

 const eco = document.getElementById("securityEconomy");
 if(eco){
  eco.innerHTML="";
  d.economy.forEach(r=>{
   const tr=document.createElement("tr");
   tr.innerHTML=`<td>${r.user_id}</td><td>${r.action}</td><td>${r.count}</td>`;
   eco.appendChild(tr);
  });
 }

}
let securityDays = 7;

function setSecurityDays(days){

 securityDays = days;

 document.getElementById("securityDaysLabel").innerText = days;

 loadSecurity();

}
document.addEventListener("DOMContentLoaded", () => {
 loadLogs();
 loadSecurity();
});