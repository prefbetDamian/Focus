<?php
require_once __DIR__ . '/../core/auth.php';

// Dostęp tylko dla kierownika (2+) – jak dashboard
$managerInfo = requireManagerPage(2);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<script>
    window.USER_ROLE = <?= (int)($_SESSION['role_level'] ?? 0) ?>;
</script>
<meta charset="UTF-8">
<title>Urlopy / L4</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{font-family:Arial;margin:0;padding:30px;background:#f3f3f3}
.container{max-width:1000px;margin:auto;background:#fff;padding:30px;border-radius:18px}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:center}
button{padding:8px 12px;border-radius:12px;border:none;cursor:pointer}
.edit{background:#4a90e2;color:#fff}
.del{background:#dc3545;color:#fff}
.back{margin-bottom:20px;background:#6c757d;color:#fff}
#btnAbsencePdf{    width:40px;    height:40px;    border-radius:50%;    background:#dc3545;    color:#fff;    font-size:18px;    cursor:pointer;}
#btnAbsencePdf:hover{    background:#b02a37;}

</style>
</head>
<body>

<div class="container">
<button class="back" onclick="goBackToEntry('dashboard.php')">⬅ Powrót</button>

<h2>📋 Urlopy / Zwolnienia (L4)</h2>


<div style="display:flex;gap:12px;align-items:center;margin-top:16px">
    <input type="month" id="absenceMonth">

    <button onclick="loadAbsences()">📆 Pokaż</button>

    <button id="btnAbsencePdf" title="Drukuj listę nieobecności">
        🖨
    </button>
</div>


<table id="absenceTable">
<thead>
<tr>
<th>Pracownik</th>
<th>Typ</th>
<th>Od</th>
<th>Do</th>
<th>Akcje</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>

<!-- MODAL -->
<div id="absenceModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6)">
<div style="background:#fff;max-width:400px;margin:10% auto;padding:20px;border-radius:18px">
<h3>✏️ Edycja</h3>

<input type="hidden" id="editId">
<p id="editEmployee"></p>

<select id="editType">
<option value="URLOP">Urlop</option>
<option value="L4">L4</option>
</select>

<input type="date" id="editFrom">
<input type="date" id="editTo">

<button onclick="saveAbsence()">💾 Zapisz</button>
<button onclick="closeAbsenceModal()">Anuluj</button>
 </div>

<script>
function goBackToEntry(fallbackUrl) {
    if (document.referrer) {
        window.location.href = document.referrer;
    } else {
        window.location.href = fallbackUrl;
    }
}
</script>
</div>

<script>
function getCurrentMonth(){
    const d = new Date();
    return d.toISOString().slice(0,7);
}

async function loadAbsences(){
    const monthInput = document.getElementById("absenceMonth");
    if(!monthInput.value){
        monthInput.value = getCurrentMonth();
    }

    const month = monthInput.value;

    const res = await fetch(`get_absences.php?month=${month}`);
    const data = await res.json();

    const tbody = document.querySelector("#absenceTable tbody");
    tbody.innerHTML = "";

    data.forEach(a => {
        const tr = document.createElement("tr");
        const isFromRequest = a.absence_request_id ? true : false;
        const badge = isFromRequest ? '<span style="background:#28a745;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;margin-left:5px;">Wniosek</span>' : '';
        
        tr.innerHTML = `
            <td>${a.last_name} ${a.first_name}${badge}</td>
            <td>${a.site_name}</td>
            <td>${a.from}</td>
            <td>${a.to}</td>
            <td>
                <button class="edit" data-roles="4,9" onclick='editAbsence(${JSON.stringify(a)})'>✏️</button>
                <button class="del" data-roles="4,9" onclick='deleteAbsence(${a.id}, ${a.absence_request_id || null})'>🗑</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Zastosuj widoczność przycisków na podstawie roli
    if (typeof applyRoleVisibility === 'function') {
        applyRoleVisibility();
    }
}

loadAbsences();


function editAbsence(a){
    // Jeśli nieobecność pochodzi z wniosku urlopowego (absence_requests)
    if(a.absence_request_id){
        if(!confirm('⚠️ Ta nieobecność pochodzi z zaakceptowanego wniosku urlopowego.\\n\\nCzy na pewno chcesz ją edytować? Zmiany zostaną zsynchronizowane z wnioskiem.')){
            return;
        }
    }
    
    // Standardowa edycja (działa dla obu źródeł)
    editId.value=a.id;
    editEmployee.innerText=a.last_name+" "+a.first_name;
    editType.value=a.site_name;
    editFrom.value=a.from;
    editTo.value=a.to;
    absenceModal.style.display="block";
}

function closeAbsenceModal(){
    absenceModal.style.display="none";
}

async function saveAbsence(){
    const r=await fetch("update_absence.php",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({
            id:editId.value,
            type:editType.value,
            from:editFrom.value,
            to:editTo.value
        })
    });
    const d=await r.json();
    alert(d.message);
    closeAbsenceModal();
    loadAbsences();
}

async function deleteAbsence(id, absenceRequestId){
    // Jeśli nieobecność pochodzi z wniosku urlopowego
    if(absenceRequestId){
        alert('Ta nieobecność pochodzi z wniosku urlopowego. Odrzuć wniosek na stronie Wnioski Urlopowe.');
        return;
    }
    
    if(!confirm("Usunąć wpis?")) return;

    const r = await fetch("delete_absence.php",{
        method:"POST",
        headers:{ "Content-Type":"application/json" },
        body: JSON.stringify({ id })
    });

    const d = await r.json();

    if(!d.success){
        alert(d.message || "Błąd usuwania");
        return;
    }

    alert("Usunięto wpis");
    loadAbsences();
}

document.getElementById("btnAbsencePdf").addEventListener("click", () => {
    const month = document.getElementById("absenceMonth").value;

    if(!month){
        alert("Wybierz miesiąc");
        return;
    }

    window.open(
        "absence_month_pdf.php?month=" + encodeURIComponent(month),
        "_blank"
    );
});

</script>

<script>
function applyRoleVisibility() {
    document.querySelectorAll('[data-roles]').forEach(el => {
        const allowed = el.dataset.roles
            .split(',')
            .map(r => parseInt(r.trim(), 10));

        if (!allowed.includes(window.USER_ROLE)) {
            el.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof applyRoleVisibility === 'function') {
        applyRoleVisibility();
    }
});
</script>

</body>
</html>
