async function loadLogs() {

 const res = await fetch('/admin/api/logs_list.php', {
   credentials: 'same-origin'
 });

 const data = await res.json();

 if (!data.ok) return;

 const tbody = document.getElementById('logs');

 tbody.innerHTML = '';

 data.logs.forEach(log => {

  const row = document.createElement('tr');

  row.innerHTML = `
    <td>${log.created_at}</td>
    <td>${log.action}</td>
    <td>${log.user_id ?? '-'}</td>
    <td>${log.target_id ?? '-'}</td>
    <td>${log.status}</td>
    <td><pre>${log.meta}</pre></td>
  `;

  tbody.appendChild(row);

 });

}

document.addEventListener('DOMContentLoaded', loadLogs);