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
         .appendTo($(column.header()).empty())
         .on('change', function () {

           const val = $.fn.dataTable.util.escapeRegex($(this).val());

           column
             .search(val ? '^'+val+'$' : '', true, false)
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

   select.val(current);

 });

}

document.addEventListener("DOMContentLoaded", loadLogs);