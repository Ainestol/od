async function loadLogs() {

 const action = document.getElementById("actionFilter")?.value || "";
 const user = document.getElementById("userFilter")?.value || "";
 const limit = document.getElementById("limitFilter")?.value || "50";

 const url = `/admin/api/logs_list.php?action=${encodeURIComponent(action)}&user_id=${encodeURIComponent(user)}&limit=${encodeURIComponent(limit)}`;

 const res = await fetch(url, {
   credentials: 'same-origin'
 });

 const data = await res.json();

 if (!data.ok) {
   console.error("Logs error:", data);
   return;
 }

 const tbody = document.getElementById('logs');
 tbody.innerHTML = '';

 data.logs.forEach(log => {

  let metaFormatted = '';

  try {
   if (log.meta) {
     metaFormatted = JSON.stringify(
       typeof log.meta === "string" ? JSON.parse(log.meta) : log.meta,
       null,
       2
     );
   }
  } catch(e) {
   metaFormatted = log.meta;
  }

  const row = document.createElement('tr');

  row.innerHTML = `
    <td>${log.created_at}</td>
    <td>${log.action}</td>
    <td>
      ${log.user_id 
        ? `<a href="/admin/users.html?id=${log.user_id}" class="link">${log.user_id}</a>` 
        : '-'}
    </td>
    <td>${log.target_id ?? '-'}</td>
    <td>${log.status}</td>
    <td><pre>${metaFormatted}</pre></td>
  `;

  tbody.appendChild(row);

 });

}


function resetFilters() {

 document.getElementById("actionFilter").value = "";
 document.getElementById("userFilter").value = "";
 document.getElementById("limitFilter").value = "50";

 loadLogs();

}


document.addEventListener('DOMContentLoaded', () => {

 loadLogs();

});


/* auto refresh každých 10 sekund */

setInterval(() => {

 loadLogs();

}, 10000);