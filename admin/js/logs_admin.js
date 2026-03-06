async function loadLogs(){

 const res = await fetch('/admin/api/logs_list.php?limit=500', {
   credentials: 'same-origin'
 });

 const data = await res.json();

 if(!data.ok) return;

 const rows = data.logs.map(log => {

  let meta = "";

  try{
   meta = JSON.stringify(
     typeof log.meta === "string" ? JSON.parse(log.meta) : log.meta,
     null,
     2
   );
  }catch(e){
   meta = log.meta;
  }

  return [
   log.created_at,
   log.action,
   log.user_id ?? "-",
   log.target_id ?? "-",
   log.status,
   `<pre>${meta}</pre>`
  ];

 });

 $('#logsTable').DataTable({
   destroy:true,
   data:rows,
   columns:[
     {title:"Time"},
     {title:"Action"},
     {title:"User"},
     {title:"Target"},
     {title:"Status"},
     {title:"Meta"}
   ],
   pageLength:50,
   order:[[0,"desc"]]
 });

}

document.addEventListener("DOMContentLoaded", loadLogs);