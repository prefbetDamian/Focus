
// Dashboard JS - wszystkie funkcje panelu kierownika

// Referencje do elementów DOM (inicjalizowane po załadowaniu strony)
let absenceEmployee, absenceType, absenceFrom, absenceTo, absenceMsg;
let eFirst, eLast, eOperator, empResult;
let siteReport, employeeSelect;

// Geofencing (mapa START pracy)
let geoMap = null;
let geoMarkersLayer = null;

async function loadEmployeesForManual() {
    const res = await fetch("../get_employees.php");
    const data = await res.json();

    const sel = document.getElementById("manualEmployee");
    if (!sel) return;
    sel.innerHTML = `<option value="">— pracownik —</option>`;

    data.forEach(e => {
        sel.innerHTML += `
            <option value="${e.id}" data-operator="${e.is_operator}">
                ${e.last_name} ${e.first_name}
            </option>`;
    });
}

async function loadSitesForManual() {
    const res = await fetch("../get_sites.php");
    const data = await res.json();

    const sel = document.getElementById("manualSite");
    if (!sel) return;
    sel.innerHTML = `<option value="">— budowa —</option>`;

    data.forEach(s => {
        sel.innerHTML += `<option value="${s.name}">${s.name}</option>`;
    });
}

async function loadMachinesForManual() {
    const res = await fetch("get_machines.php");
    const data = await res.json();

    const sel = document.getElementById("manualMachine");
    if (!sel) return;
    sel.innerHTML = `<option value="">— wybierz maszynę —</option>`;

    data.forEach(m => {
        sel.innerHTML += `
            <option value="${m.id}">
                ${m.registry_number} - ${m.machine_name}
            </option>`;
    });
}

function onManualEmployeeChange() {
    const sel = document.getElementById("manualEmployee");
    const opt = sel.options[sel.selectedIndex];
    const isOperator = opt?.dataset.operator === "1";

    const machineSel = document.getElementById("manualMachine");

    if (isOperator) {
        machineSel.style.display = "block";
        loadMachinesForManual();
    } else {
        machineSel.style.display = "none";
        machineSel.value = "";
    }
}

async function addManualSession() {
    const employee_id = manualEmployee.value;
    const site_name   = manualSite.value;
    const date        = manualDate.value;
    const machine_id  = manualMachine.value || null;
    const comment     = manualComment.value.trim();

    if (!employee_id || !site_name || !date) {
        manualSessionMsg.innerText = "UzupeĹ‚nij wszystkie pola";
        return;
    }

    if (comment.length < 3) {
        manualSessionMsg.innerText = "Podaj powĂłd";
        return;
    }

    const res = await fetch("add_manual_session.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({
            employee_id,
            site_name,
            date,
            machine_id,
            comment
        })
    });

    const data = await res.json();
    manualSessionMsg.innerText = data.message;
    manualSessionMsg.classList.add('show');
    
    if (data.success) {
        manualSessionMsg.style.background = '#d4edda';
        manualSessionMsg.style.color = '#155724';
        // Wyczyść pola
        manualEmployee.value = '';
        manualSite.value = '';
        manualDate.value = '';
        manualMachine.value = '';
        manualComment.value = '';
        document.getElementById('manualMachine').style.display = 'none';
    } else {
        manualSessionMsg.style.background = '#f8d7da';
        manualSessionMsg.style.color = '#721c24';
    }
}


function goToAbsences(){
    window.location.href = "absences.php";
}


async function loadEmployeesForAbsence() {
    const res = await fetch("../get_employees.php");
    const data = await res.json();

    const select = document.getElementById("absenceEmployee");
    const infoSpan = document.getElementById("absenceVacationDays");
    if (!select) return;
    select.innerHTML = `<option value="">— Wybierz pracownika —</option>`;

    data.forEach(e => {
        const opt = document.createElement("option");
        opt.value = e.id;
        opt.textContent = `${e.last_name} ${e.first_name}`;
        if (typeof e.vacation_days !== 'undefined') {
            opt.dataset.vacationDays = String(e.vacation_days);
        }
        select.appendChild(opt);
    });

    // Po inicjalnym załadowaniu wyczyść info o urlopie
    if (infoSpan) {
        infoSpan.textContent = '-';
    }

    // Aktualizuj dostępne dni urlopu przy zmianie pracownika
    select.addEventListener('change', () => {
        if (!infoSpan) return;
        const selectedOption = select.options[select.selectedIndex];
        const days = selectedOption?.dataset?.vacationDays;
        infoSpan.textContent = days !== undefined ? days : '-';
    });
}

// loadEmployeesForAbsence(); - wywołane w DOMContentLoaded

async function addAbsence() {
    const employee_id = absenceEmployee.value;
    const type        = absenceType.value;
    const from        = absenceFrom.value;
    const to          = absenceTo.value;

    if (!employee_id || !from || !to) {
        absenceMsg.innerText = "UzupeĹ‚nij wszystkie pola";
        return;
    }

    const res = await fetch("add_absence.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({
            employee_id,
            type,
            from,
            to
        })
    });

    const data = await res.json();

    absenceMsg.innerText = data.message;
    absenceMsg.classList.add('show');
    
    if (data.success) {
        absenceMsg.style.background = '#d4edda';
        absenceMsg.style.color = '#155724';
        // Zaktualizuj liczbę dni urlopowych, jeśli backend ją zwrócił
        if (typeof data.vacation_days !== 'undefined') {
            const infoSpan = document.getElementById('absenceVacationDays');
            const selectEl = document.getElementById('absenceEmployee');
            if (infoSpan) {
                infoSpan.textContent = data.vacation_days;
            }
            // Zaktualizuj również atrybut w option, jeśli pracownik nadal jest wybrany
            if (selectEl && selectEl.value === String(employee_id)) {
                const opt = selectEl.options[selectEl.selectedIndex];
                if (opt) {
                    opt.dataset.vacationDays = String(data.vacation_days);
                }
            }
        }
        // Wyczyść pola
        absenceEmployee.value = '';
        absenceFrom.value = '';
        absenceTo.value = '';
    } else {
        absenceMsg.style.background = '#f8d7da';
        absenceMsg.style.color = '#721c24';
    }
}


/*
async function loadEmployeesForSiteReport() {
    const res = await fetch("../get_employees.php");
    const data = await res.json();

    const select = document.getElementById("siteEmployeeSelect");
    select.innerHTML = `<option value="">— Wybierz pracownika —</option>`;

    data.forEach(e => {
        const opt = document.createElement("option");
        opt.value = `${e.last_name}|${e.first_name}`;
        opt.textContent = `${e.last_name} ${e.first_name}`;
        select.appendChild(opt);
    });
}

// loadEmployeesForSiteReport(); - zakomentowana funkcja
*/


function exportSitePDF(type) {

    const siteSelectId  = type === 'k' ? 'siteReportk'      : 'siteReport';
    const monthInputId = type === 'k' ? 'siteMonthPickerk' : 'siteMonthPicker';
    const endpoint     = type === 'k'
        ? 'export_site_pdfkier.php'
        : 'export_site_pdf.php';

    const site  = document.getElementById(siteSelectId)?.value;
    const month = document.getElementById(monthInputId)?.value;

    if (!site || !month) {
        alert("Wybierz budowę i miesiąc");
        return;
    }

    const [year, m] = month.split("-");

    const url =
        `${endpoint}`
        + `?site=${encodeURIComponent(site)}`
        + `&month=${m}`
        + `&year=${year}`;

    window.open(url, "_blank");
}


/* ZAĹADUJ MASZYNY DO RAPORTU TANKOWAĹ */
function loadFuelMachines() {
    const select = document.getElementById("fuelMachineSelect");
    if (!select) return;

    fetch("get_machines.php")
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">— wybierz maszynę —</option>';

            data.forEach(m => {
                const o = document.createElement("option");
                o.value = m.id;
                o.textContent = `${m.registry_number} - ${m.machine_name}`;
                select.appendChild(o);
            });
        });
}

// loadFuelMachines(); - wywołane w DOMContentLoaded

function loadMachinesForManualFuel() {
    const select = document.getElementById("mfMachine");
    if (!select) return;

    fetch("get_machines.php")
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">— Wybierz maszynę —</option>';

            data.forEach(m => {
                const o = document.createElement("option");
                o.value = m.id;
                o.textContent = `${m.registry_number} - ${m.machine_name}`;
                select.appendChild(o);
            });
        })
        .catch(err => {
            console.error('Błąd ładowania maszyn do ręcznego tankowania:', err);
        });
}

/* EXPORT PDF */
function exportFuelMachinePDF() {
    const machineId = document.getElementById("fuelMachineSelect").value;
    const month     = document.getElementById("fuelMonth").value;

    if (!machineId || !month) {
        alert("Wybierz maszynę i miesiąc");
        return;
    }

    window.open(
        `export_fuel_machine_pdf.php?machine_id=${machineId}&month=${month}`,
        "_blank"
    );
}



async function loadMachinesForReport() {
    const res = await fetch("get_machines.php");
    const data = await res.json();

    const select = document.getElementById("machineSelect");
    if (!select) return;
    select.innerHTML = `<option value="">— Wybierz maszynę —</option>`;

    data.forEach(m => {
        const opt = document.createElement("option");
        opt.value = m.id;
        opt.textContent = `${m.registry_number} - ${m.machine_name}`;
        select.appendChild(opt);
    });
}

// loadMachinesForReport(); - wywołane w DOMContentLoaded


function exportMachinePDF() {
    const machineId = document.getElementById("machineSelect").value;
    const monthVal  = document.getElementById("machineMonth").value;

    if (!machineId) {
        alert("Wybierz maszynÄ™");
        return;
    }

    if (!monthVal) {
        alert("Wybierz miesiÄ…c");
        return;
    }

    const [year, month] = monthVal.split("-");

    const url =
        `export_machine_pdf.php?machineId=${machineId}&month=${month}&year=${year}`;

    window.open(url, '_blank');
}


async function employeeReport() {
    const [lastName, firstName] =
        document.getElementById("employeeSelect").value.split("|");

    const monthValue = document.getElementById("monthPicker").value;
    if (!monthValue) {
        alert("Wybierz miesiÄ…c");
        return;
    }

    const [year, month] = monthValue.split("-");

    const res = await fetch("../report_employee.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({
            firstName,
            lastName,
            month,
            year
        })
    });

    const data = await res.json();
    employeeResult.innerText =
        "Suma godzin: " + (data.total_time ?? "0:00");
}


async function siteReportFn() {
    const res = await fetch("../report_site.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({ siteName: siteReport.value })
    });

    const data = await res.json();
    siteResult.innerText =
        "ĹÄ…czny czas: " + (data.total_time ?? "0:00");
}



async function loadEmployees() {
    const res = await fetch("../get_employees.php");
    const data = await res.json();

    const select = document.getElementById("employeeSelect");
    if (!select) return;
    select.innerHTML = "";

    data.forEach(e => {
        const option = document.createElement("option");
        option.value = `${e.last_name}|${e.first_name}`;
        option.textContent = `${e.last_name} ${e.first_name}`;
        select.appendChild(option);
    });
}

// loadEmployees(); - wywołane w DOMContentLoaded


async function addEmployee() {
    const firstName = eFirst.value.trim();
    const lastName  = eLast.value.trim();
    const operator  = eOperator.value; // wartość z selecta (0 lub 1)
    const hourRate  = parseFloat(document.getElementById('empHourRate').value) || 0;
    const vacationDays = parseInt(document.getElementById('empVacationDays').value, 10) || 0;

    if (!firstName || !lastName) {
        empResult.innerText = "Uzupełnij imię i nazwisko";
        empResult.classList.add('show');
        empResult.style.background = '#f8d7da';
        empResult.style.color = '#721c24';
        return;
    }

    const res = await fetch("../add_employee.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            firstName,
            lastName,
            operator,
            hour_rate: hourRate,
            vacation_days: vacationDays
        })
    });

    const data = await res.json();

    empResult.classList.add('show');
    
    if (data.success) {
        empResult.innerHTML =
            `✅ <strong>Pracownik dodany!</strong><br>
            🔑 <strong>TOKEN (10 znaków):</strong> <code style="background:#f0f0f0;padding:5px;font-size:16px;border-radius:4px;">${data.token}</code><br>
            <button onclick="printToken('${data.token}', '${firstName}', '${lastName}')" style="margin-top:15px;padding:10px 20px;background:#667eea;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
                🖨️ Drukuj token z instrukcją
            </button>
            <small style="color:#666;margin-top:8px;display:block;">
                📝 Przekaż token pracownikowi. Ustawi PIN na: <a href="../set_pin.html" target="_blank" style="color:#667eea;">set_pin.html</a>
            </small>`;
        empResult.style.background = '#d4edda';
        empResult.style.color = '#155724';
        eFirst.value = "";
        eLast.value = "";
        eOperator.selectedIndex = 0;
        document.getElementById('empHourRate').value = "";
        loadEmployees();
    } else {
        empResult.innerText = data.message;
        empResult.style.background = '#f8d7da';
        empResult.style.color = '#721c24';
    }
}

async function addMachine() {
    const nameEl        = document.getElementById('mName');
    const shortEl       = document.getElementById('mShort');
    const ownerEl       = document.getElementById('mOwner');
    const renterEl      = document.getElementById('mRenter');
    const hourRateEl    = document.getElementById('mHourRate');
    const registryEl    = document.getElementById('mRegistry');
    const workshopTagEl = document.getElementById('mWorkshopTag');
    const fuelNormEl    = document.getElementById('mFuelNorm');

    const machine_name    = nameEl ? nameEl.value.trim() : "";
    const short_name      = shortEl ? shortEl.value.trim() : "";
    const owner           = ownerEl ? ownerEl.value : "";
    const registry_number = registryEl ? registryEl.value.trim() : "";
    const renter          = renterEl ? renterEl.value.trim() : "";
    const hourRateRaw     = hourRateEl ? hourRateEl.value.trim() : "";
    const workshop_tag    = workshopTagEl ? workshopTagEl.value.trim() : "";
    const fuelNormRaw     = fuelNormEl ? fuelNormEl.value.trim() : "";

    if (!machine_name || !owner || !registry_number) {
        machineMsg.innerText = "Uzupełnij wymagane pola (nazwa, właściciel, numer)";
        machineMsg.classList.add('show');
        machineMsg.style.background = '#f8d7da';
        machineMsg.style.color = '#721c24';
        return;
    }

    let hour_rate = 0;
    if (hourRateRaw !== "") {
        const parsed = parseFloat(hourRateRaw.replace(',', '.'));
        if (Number.isNaN(parsed) || parsed < 0) {
            machineMsg.innerText = "Podaj poprawną stawkę (liczba ≥ 0)";
            machineMsg.classList.add('show');
            machineMsg.style.background = '#f8d7da';
            machineMsg.style.color = '#721c24';
            return;
        }
        hour_rate = parsed;
    }

    let fuel_norm_l_per_mh = null;
    if (fuelNormRaw !== "") {
        const parsed = parseFloat(fuelNormRaw.replace(',', '.'));
        if (Number.isNaN(parsed) || parsed < 0) {
            machineMsg.innerText = "Podaj poprawną normę spalania (liczba ≥ 0)";
            machineMsg.classList.add('show');
            machineMsg.style.background = '#f8d7da';
            machineMsg.style.color = '#721c24';
            return;
        }
        fuel_norm_l_per_mh = parsed;
    }

    const payload = {
        machine_name,
        short_name,
        owner,
        registry_number,
        renter,
        workshop_tag,
        hour_rate,
        fuel_norm_l_per_mh
    };

    const res = await fetch("add_machine.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (data.success) {
        const msg = data.message || 'Dodano maszynę';
        machineMsg.innerHTML =
            `${msg}<br><b>${machine_name}</b> (${registry_number})`;
        machineMsg.classList.add('show');
        machineMsg.style.background = '#d4edda';
        machineMsg.style.color = '#155724';

        if (nameEl) nameEl.value = "";
        if (shortEl) shortEl.value = "";
        if (ownerEl) ownerEl.value = "";
        if (renterEl) renterEl.value = "";
        if (hourRateEl) hourRateEl.value = "";
        if (registryEl) registryEl.value = "";
        if (workshopTagEl) workshopTagEl.value = "";
        if (fuelNormEl) fuelNormEl.value = "";
    } else {
        machineMsg.innerText = data.message || 'Błąd podczas dodawania maszyny';
        machineMsg.classList.add('show');
        machineMsg.style.background = '#f8d7da';
        machineMsg.style.color = '#721c24';
    }
}


async function exportPDF() {
    if (!employeeSelect.value) {
        alert("Wybierz pracownika");
        return;
    }
    if (!monthPicker.value) {
        alert("Wybierz miesiÄ…c");
        return;
    }

    const [lastName, firstName] = employeeSelect.value.split("|");
    const [year, month] = monthPicker.value.split("-");

    const url = `export_pdf.php?firstName=${encodeURIComponent(firstName)}&lastName=${encodeURIComponent(lastName)}&month=${month}&year=${year}`;
    window.open(url, '_blank');
}


function exportAllEmployees() {
    const monthValue = document.getElementById("allMonthPicker").value;

    if (!monthValue) {
        alert("Wybierz miesiÄ…c");
        return;
    }

    const [year, month] = monthValue.split("-");

    const url =
        `export_all_employees_pdf.php?month=${month}&year=${year}`;

    window.open(url, '_blank');
}


// ===== GEOFENCING: MAPA START PRACY =====

function initGeoMap() {
    const mapEl = document.getElementById('geoMap');
    if (!mapEl || typeof L === 'undefined') {
        return;
    }

    if (geoMap) {
        return;
    }

    // Domyślne centrum: Polska
    geoMap = L.map('geoMap').setView([52.0, 19.0], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributorzy'
    }).addTo(geoMap);

    geoMarkersLayer = L.layerGroup().addTo(geoMap);

    // Ustaw domyślną datę na dziś, jeśli puste
    const dateInput = document.getElementById('geoDate');
    if (dateInput && !dateInput.value) {
        const today = new Date().toISOString().slice(0, 10);
        dateInput.value = today;
    }

    reloadGeoMap();
}

async function reloadGeoMap() {
    const mapEl = document.getElementById('geoMap');
    if (!mapEl || !geoMap) return;

    const infoEl = document.getElementById('geoMapInfo');
    const dateInput = document.getElementById('geoDate');
    const date = dateInput && dateInput.value ? dateInput.value : '';

    let url = 'get_geofence_points.php';
    if (date) {
        url += '?date=' + encodeURIComponent(date);
    }

    if (infoEl) {
        infoEl.textContent = 'Ładuję punkty lokalizacji...';
    }

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            if (infoEl) {
                infoEl.textContent = data.message || 'Błąd ładowania punktów geofencingu.';
            }
            return;
        }

        const points = Array.isArray(data.points) ? data.points : [];

        geoMarkersLayer.clearLayers();

        if (!points.length) {
            if (infoEl) {
                infoEl.textContent = 'Brak startów pracy z lokalizacją w wybranym dniu.';
            }
            return;
        }

        const bounds = [];

        points.forEach(p => {
            if (p.lat === null || p.lng === null) return;

            const lat = parseFloat(p.lat);
            const lng = parseFloat(p.lng);
            if (Number.isNaN(lat) || Number.isNaN(lng)) return;

            const popup = [
                `👷 ${p.first_name || ''} ${p.last_name || ''}`.trim(),
                p.site_name ? `🏗️ ${p.site_name}` : '',
                p.start_time ? `⏱️ ${new Date(p.start_time).toLocaleString('pl-PL')}` : '',
                p.location_source ? `📍 Źródło: ${p.location_source}` : ''
            ].filter(Boolean).join('<br>');

            const marker = L.marker([lat, lng]);
            if (popup) {
                marker.bindPopup(popup);
            }
            marker.addTo(geoMarkersLayer);
            bounds.push([lat, lng]);
        });

        if (bounds.length) {
            geoMap.fitBounds(bounds, { padding: [20, 20] });
            if (infoEl) {
                infoEl.textContent = `Pokazuję ${bounds.length} punkt(ów) START pracy w dniu ${data.date}.`;
            }
        } else if (infoEl) {
            infoEl.textContent = 'Brak poprawnych współrzędnych do pokazania.';
        }
    } catch (e) {
        console.error('Błąd reloadGeoMap:', e);
        if (infoEl) {
            infoEl.textContent = 'Błąd ładowania danych z serwera.';
        }
    }
}


async function addSite() {
    const name = newSite.value.trim();
    const resultDiv = document.getElementById('siteResult');
    
    if (!name) {
        resultDiv.innerText = 'Podaj nazwę budowy';
        resultDiv.classList.add('show');
        resultDiv.style.background = '#f8d7da';
        resultDiv.style.color = '#721c24';
        return;
    }

    const res = await fetch("../add_site.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({name})
    });

    const data = await res.json();
    resultDiv.innerText = data.message || '✓ Budowa dodana';
    resultDiv.classList.add('show');
    
    if (data.success) {
        resultDiv.style.background = '#d4edda';
        resultDiv.style.color = '#155724';
        newSite.value="";
    } else {
        resultDiv.style.background = '#f8d7da';
        resultDiv.style.color = '#721c24';
    }
}



function goToEmployees() {
    window.location.href = "employees.php";
}


function goToSites() {
    window.location.href = "sites.php";
}

function goToMachines() {
    window.location.href = "machines.php";
}

// ===== SESJE DO AKCEPTACJI =====

async function loadPendingSessions() {
    if (typeof window.USER_ROLE === 'number' && ![2, 9].includes(window.USER_ROLE)) {
        return;
    }

    const container = document.getElementById('pendingSessionsList');
    if (!container) return;

    try {
        const res = await fetch('get_pending_sessions.php');
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = `<div class="session-empty">Błąd ładowania sesji do akceptacji</div>`;
            return;
        }

        const sessions = data.sessions || [];

        if (!sessions.length) {
            container.innerHTML = `<div class="session-empty">✅ Brak sesji oczekujących na akceptację</div>`;
            return;
        }

        container.innerHTML = sessions.map(s => {
            const durationHours = s.duration_seconds
                ? (s.duration_seconds / 3600).toFixed(2)
                : '-';

            const approvedCount = Number(s.approved_count || 0);
            const approvalsTotal = Number(s.approvals_total || 0);

            const approvalsInfo = approvalsTotal > 0
                ? `Akceptacje: ${approvedCount}/${approvalsTotal}`
                : '';

            const isSpecialSite =
                Number(s.site_id || 0) === 26 ||
                String(s.site_name || '').toLowerCase().includes('niesklasyfik');

            return `
                <div class="session-card">
                    <div class="session-header">
                        <div class="session-employee">👷 ${s.first_name} ${s.last_name}</div>
                        <div class="session-site">🏗️ ${s.site_name || ''}</div>
                    </div>
                    <div class="session-meta">
                        <span>Od: ${s.start_time ? new Date(s.start_time).toLocaleString('pl-PL') : '-'}</span>
                        <span>Do: ${s.end_time ? new Date(s.end_time).toLocaleString('pl-PL') : '-'}</span>
                        <span>Czas: ${durationHours} h</span>
                        ${approvalsInfo ? `<span>${approvalsInfo}</span>` : ''}
                    </div>
                    <select id="session-comment-${s.id}" class="session-comment">
                        <option value="">— wybierz powód / komentarz —</option>
                        ${isSpecialSite
                            ? '<option value="" data-dynamic-comment="1">Pracowałeś na innej budowie (wybierz poniżej)</option>'
                            : ''}
                        <option value="Nie zgadza się czas pracy">Nie zgadza się czas pracy</option>
                        <option value="Brak potwierdzenia obecności">Brak potwierdzenia obecności</option>
                        <option value="Inny powód (opisany ustnie)">Inny powód (opisany ustnie)</option>
                    </select>
                    <div class="session-actions">
                        <button class="btn-approve" onclick="approveSession(${s.id})">✅ Akceptuj</button>
                        ${isSpecialSite
                            ? `<button class="btn-reject" onclick="toggleRejectDetails(${s.id})">❌ Odrzuć / zmień budowę</button>`
                            : `<button class="btn-reject" onclick="rejectSession(${s.id})">❌ Odrzuć</button>`
                        }
                    </div>
                    ${isSpecialSite ? `
                    <div class="session-reject-details" id="reject-details-${s.id}" style="display:none; margin-top:8px; font-size:13px;">
                        <div style="margin-bottom:6px;">Czy pracownik pracował na innej budowie? Jeśli tak, wybierz właściwą przed odrzuceniem.</div>
                        <label>Popraw budowę:
                            <select id="reject-site-${s.id}" style="margin-left:6px; padding:3px 6px;">
                                <option value="">— wybierz budowę —</option>
                            </select>
                        </label>
                        <div style="margin-top:6px; display:flex; gap:8px; flex-wrap:wrap;">
                            <button class="btn-reject" onclick="rejectSession(${s.id})">❌ Zapisz zmianę i odrzuć</button>
                            <button type="button" class="btn-approve" style="background:#6c757d;" onclick="toggleRejectDetails(${s.id}, true)">Anuluj</button>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    } catch (e) {
        console.error('Błąd loadPendingSessions:', e);
        container.innerHTML = `<div class="session-empty">Błąd ładowania sesji do akceptacji</div>`;
    }
}

async function approveSession(id) {
    const commentField = document.getElementById(`session-comment-${id}`);
    const comment = commentField ? String(commentField.value || '').trim() : '';

    try {
        const res = await fetch('approve_session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, comment })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Błąd akceptacji sesji');
            return;
        }
        await loadPendingSessions();
    } catch (e) {
        console.error('Błąd approveSession:', e);
        alert('Błąd akceptacji sesji');
    }
}

// Pokazanie / ukrycie panelu odrzucenia z możliwością poprawy budowy
async function toggleRejectDetails(id, hideOnly = false) {
    const details = document.getElementById(`reject-details-${id}`);
    if (!details) {
        // Brak panelu szczegółów oznacza zwykłą sesję (nie site_id=26) – nie robimy nic.
        return;
    }

    if (hideOnly) {
        details.style.display = 'none';
        return;
    }

    if (details.style.display === 'block') {
        details.style.display = 'none';
        return;
    }

    // Zanim pokażemy panel, zapytaj kierownika czy faktycznie chce wskazać inną budowę
    const confirmChange = confirm('Czy pracownik pracował na innej budowie niż wskazana?\n\nOK – tak, chcę wskazać inną budowę.\nAnuluj – nie, odrzuć bez zmiany budowy.');
    if (!confirmChange) {
        // Odrzucenie bez zmiany budowy
        rejectSession(id);
        return;
    }

    // Przy pierwszym otwarciu wypełnij listę budów
    const siteSelect = document.getElementById(`reject-site-${id}`);
    if (siteSelect && siteSelect.options.length <= 1) {
        try {
            const res = await fetch('../get_sites.php');
            const sites = await res.json();

            siteSelect.innerHTML = '<option value="">— wybierz budowę —</option>';
            sites.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                siteSelect.appendChild(opt);
            });
        } catch (e) {
            console.error('Błąd ładowania listy budów do odrzucenia:', e);
            alert('Nie udało się załadować listy budów. Spróbuj ponownie.');
            return;
        }
    }

    // Jeśli jest dostępny selektor komentarza z dynamiczną opcją,
    // podpinamy aktualizację tak, aby komentarz przyjął postać
    // "Pracowałeś na budowie NAZWA_BUDOWY" na podstawie wybranej budowy.
    const commentSelect = document.getElementById(`session-comment-${id}`);
    if (siteSelect && commentSelect) {
        const dynamicOption = commentSelect.querySelector('option[data-dynamic-comment="1"]');
        if (dynamicOption) {
            siteSelect.addEventListener('change', () => {
                const selected = siteSelect.options[siteSelect.selectedIndex];
                if (!selected || !selected.value) {
                    dynamicOption.textContent = 'Pracowałeś na innej budowie (wybierz poniżej)';
                    dynamicOption.value = '';
                    if (commentSelect.value === dynamicOption.value) {
                        commentSelect.value = '';
                    }
                    return;
                }
                const siteName = selected.textContent;
                const text = `Pracowałeś na budowie ${siteName}`;
                dynamicOption.textContent = text;
                dynamicOption.value = text;
                commentSelect.value = text;
            }, { once: true });
        }
    }

    details.style.display = 'block';
}

async function rejectSession(id) {
    const commentField = document.getElementById(`session-comment-${id}`);
    const comment = commentField ? String(commentField.value || '').trim() : '';

    if (!comment) {
        alert('Przy odrzuceniu sesji komentarz jest wymagany.');
        if (commentField) {
            commentField.focus();
        }
        return;
    }

    const siteSelect = document.getElementById(`reject-site-${id}`);
    const newSiteId = siteSelect && siteSelect.value ? parseInt(siteSelect.value, 10) : null;

    try {
        const res = await fetch('reject_session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, comment, new_site_id: newSiteId })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Błąd odrzucania sesji');
            return;
        }
        // Po odrzuceniu i ewentualnej zmianie budowy schowaj panel szczegółów
        const details = document.getElementById(`reject-details-${id}`);
        if (details) {
            details.style.display = 'none';
        }
        await loadPendingSessions();
    } catch (e) {
        console.error('Błąd rejectSession:', e);
        alert('Błąd odrzucania sesji');
    }
}


async function loadSitesForReport() {
    const res = await fetch("../get_sites.php");
    const sites = await res.json();

    const selects = [
        document.getElementById("siteReport"),
        document.getElementById("siteReportk")
    ].filter(Boolean); // usuwa null

    if (selects.length === 0) {
        return;
    }

    selects.forEach(select => {
        select.innerHTML = `<option value="">— Wybierz budowę —</option>`;

        sites.forEach(s => {
            const opt = document.createElement("option");
            opt.value = s.name;
            opt.textContent = s.name;
            select.appendChild(opt);
        });
    });
}

// loadSitesForReport(); - wywołane w DOMContentLoaded


async function addManualFuel() {
    const machineEl = document.getElementById('mfMachine');
    const litersEl = document.getElementById('mfLiters');
    const meterEl = document.getElementById('mfMeterMh');
    const resultDiv = document.getElementById('mfResult');

    if (!machineEl || !litersEl || !meterEl || !resultDiv) {
        return;
    }

    const machine_id = machineEl.value;
    const litersRaw = litersEl.value.trim();
    const meterRaw = meterEl.value.trim();

    resultDiv.classList.add('show');

    if (!machine_id || !litersRaw || !meterRaw) {
        resultDiv.innerText = 'Uzupełnij wszystkie pola (maszyna, litry, stan licznika)';
        resultDiv.style.background = '#f8d7da';
        resultDiv.style.color = '#721c24';
        return;
    }

    const liters = parseFloat(litersRaw.replace(',', '.'));
    const meter_mh = parseFloat(meterRaw.replace(',', '.'));

    if (Number.isNaN(liters) || liters <= 0 || Number.isNaN(meter_mh) || meter_mh <= 0) {
        resultDiv.innerText = 'Podaj poprawne wartości liczbowe (litry > 0, m-h > 0)';
        resultDiv.style.background = '#f8d7da';
        resultDiv.style.color = '#721c24';
        return;
    }

    try {
        const res = await fetch('save_manual_fuel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                machine_id,
                liters,
                meter_mh
            })
        });

        const data = await res.json();

        if (data.success) {
            const extraInfo = [];
            if (typeof data.delta_mh !== 'undefined' && data.delta_mh !== null) {
                extraInfo.push(`Δ m-h: ${data.delta_mh}`);
            }
            if (typeof data.avg_l_per_mh !== 'undefined' && data.avg_l_per_mh !== null) {
                extraInfo.push(`Śr. l/m-h: ${data.avg_l_per_mh}`);
            }

            resultDiv.innerText = (data.message || 'Tankowanie zapisane pomyślnie') + (extraInfo.length ? `\n${extraInfo.join(' | ')}` : '');
            resultDiv.style.background = '#d4edda';
            resultDiv.style.color = '#155724';

            // Wyczyść pola
            litersEl.value = '';
            meterEl.value = '';
            machineEl.value = '';
        } else {
            resultDiv.innerText = data.message || 'Błąd podczas zapisu tankowania';
            resultDiv.style.background = '#f8d7da';
            resultDiv.style.color = '#721c24';
        }
    } catch (e) {
        console.error('Błąd zapisu ręcznego tankowania:', e);
        resultDiv.innerText = 'Błąd połączenia z serwerem';
        resultDiv.style.background = '#f8d7da';
        resultDiv.style.color = '#721c24';
    }
}


/* ===== OPERATOR ⇄ MASZYNA (legacy, nieużywane w nowym UI) ===== */
function toggleMachineBtn() {
    const btn = document.getElementById("addMachineBtn");
    if (!btn) return;
    const isOperator = document.getElementById("eOperator").checked;
    btn.style.display = isOperator ? "block" : "none";
}

// Funkcje modala pozostawione dla zgodności, ale nic nie robią,
// bo nowy UI dodawania maszyny jest w głównej sekcji dashboardu.
function openMachineModal() {}
function closeMachineModal() {}

/* ===== DRUKOWANIE TOKENA PRACOWNIKA ===== */
function printToken(token, firstName, lastName) {
    const printWindow = window.open("", "_blank");
    if (!printWindow) {
        alert("Przeglądarka zablokowała okno drukowania. Odblokuj wyskakujące okna.");
        return;
    }

    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="pl">
        <head>
            <meta charset="UTF-8">
            <title>Token dostępu - ${firstName} ${lastName}</title>
            <style>
                body {
                    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    background: #f5f7fb;
                    margin: 0;
                    padding: 40px;
                    color: #333;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                    color: #667eea;
                }
                .header p {
                    margin: 4px 0 0;
                    color: #555;
                }
                .token-box {
                    background: white;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                    text-align: center;
                    margin-bottom: 30px;
                }
                .token-label {
                    text-transform: uppercase;
                    font-size: 12px;
                    letter-spacing: 1px;
                    color: #888;
                    margin-bottom: 10px;
                }
                .token-value {
                    font-size: 28px;
                    font-weight: bold;
                    color: #222;
                    letter-spacing: 4px;
                    font-family: "Courier New", monospace;
                }
                .employee-info {
                    background: #e7f3ff;
                    padding: 16px 20px;
                    border-radius: 8px;
                    margin-bottom: 24px;
                }
                .employee-info strong {
                    color: #333;
                    font-size: 16px;
                }
                .instructions {
                    margin-top: 20px;
                }
                .instructions h2 {
                    color: #667eea;
                    font-size: 20px;
                    margin-bottom: 16px;
                }
                .step {
                    background: white;
                    border-left: 4px solid #667eea;
                    padding: 12px 16px;
                    margin-bottom: 10px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
                }
                .step-number {
                    display: inline-block;
                    width: 26px;
                    height: 26px;
                    background: #667eea;
                    color: white;
                    border-radius: 50%;
                    text-align: center;
                    line-height: 26px;
                    font-weight: bold;
                    margin-right: 8px;
                    font-size: 14px;
                }
                .url {
                    background: #f8f9fa;
                    padding: 6px 10px;
                    border-radius: 4px;
                    font-family: monospace;
                    color: #667eea;
                    display: inline-block;
                    margin-top: 4px;
                    font-size: 13px;
                }
                .note {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 12px 16px;
                    margin-top: 20px;
                    border-radius: 4px;
                    font-size: 13px;
                }
                @media print {
                    body { padding: 20px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
    <div class="header">
        <h1>🔑 TOKEN DOSTĘPU DO SYSTEMU RCP</h1>
        <p>Rejestr Czasu Pracy</p>
    </div>

    <div class="employee-info">
        <strong>👤 Pracownik:</strong> ${firstName} ${lastName}
    </div>

    <div class="token-box">
        <div class="token-label">Twój Token (10 znaków):</div>
        <div class="token-value">${token}</div>
    </div>

    <div class="instructions">
        <h2>📝 Instrukcja aktywacji konta</h2>
        
        <div class="step">
            <span class="step-number">1</span>
            <strong>Otwórz stronę aktywacyjną</strong><br>
            Wejdź na adres:<br>
            <span class="url">https://praca.pref-bet.com/set_pin.html</span>
        </div>

        <div class="step">
            <span class="step-number">2</span>
            <strong>Wpisz token</strong><br>
            Przepisz 10-znakowy token z ramki powyżej do pola "Token"
        </div>

        <div class="step">
            <span class="step-number">3</span>
            <strong>Ustaw swój PIN</strong><br>
            Wymyśl i wpisz 4-cyfrowy PIN (np. 1234)<br>
            <small style="color:#666;">⚠️ Zapamiętaj PIN - będzie potrzebny do logowania</small>
        </div>

        <div class="step">
            <span class="step-number">4</span>
            <strong>Zaloguj się</strong><br>
            Po ustawieniu PIN wróć na stronę główną:<br>
            <span class="url">https://praca.pref-bet.com</span><br>
            Zaloguj się swoim imieniem, nazwiskiem i PIN-em
        </div>

        <div class="step">
            <span class="step-number">5</span>
            <strong>Instrukcje dla użytkowników – powiadomienia PUSH i lokalizacja (wymagane)</strong><br>
            Aby system RCP działał poprawnie, wymagane jest włączenie powiadomień PUSH oraz zezwolenie na dostęp do lokalizacji (GPS) w telefonie.<br><br>
            Dla Androida: „Używaj Chrome/Edge/Firefox, nie wbudowanej przeglądarki producenta. Włącz powiadomienia w przeglądarce oraz zezwól na udostępnianie lokalizacji (GPS) dla strony z panelem RCP.”<br>
            Dla iOS: „iOS ≥ 16.4, otwórz Safari → nasz adres → Udostępnij → Dodaj do ekranu początkowego. Potem uruchom panel z ikony PWA, włącz powiadomienia oraz zezwól na dostęp do lokalizacji (GPS).”
        </div>
    </div>

    <div class="note">
        <strong>⚠️ WAŻNE:</strong><br>
        • Token możesz użyć wielokrotnie do zmiany PIN-u<br>
        • Zachowaj token w bezpiecznym miejscu<br>
        • W razie problemów skontaktuj się z kierownikiem
    </div>

    <div class="no-print" style="margin-top: 40px; text-align: center;">
        <button onclick="window.print()" style="padding: 15px 30px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold;">
            🖨️ Drukuj
        </button>
        <button onclick="window.close()" style="padding: 15px 30px; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold; margin-left: 10px;">
            ✖️ Zamknij
        </button>
    </div>

    <div style="margin-top: 40px; text-align: center; color: #999; font-size: 12px;">
        Wygenerowano: ${new Date().toLocaleString('pl-PL')}
    </div>
</body>
        </html>
    `);

    printWindow.document.close();
}


/* ===== ROLE VISIBILITY ===== */
function applyRoleVisibility() {
    document.querySelectorAll('[data-roles]').forEach(el => {
        const allowedRoles = el.dataset.roles
            .split(',')
            .map(r => parseInt(r.trim(), 10));

        if (!allowedRoles.includes(window.USER_ROLE)) {
            el.style.display = 'none';
        }
    });
}

/* ===== INIT ===== */
document.addEventListener('DOMContentLoaded', () => {

    // Inicjalizacja referencji do elementów DOM
    absenceEmployee = document.getElementById("absenceEmployee");
    absenceType = document.getElementById("absenceType");
    absenceFrom = document.getElementById("absenceFrom");
    absenceTo = document.getElementById("absenceTo");
    absenceMsg = document.getElementById("absenceMsg");
    
    eFirst = document.getElementById("empFirstName");
    eLast = document.getElementById("empLastName");
    eOperator = document.getElementById("empIsOperator");
    empResult = document.getElementById("empResult");
    
    siteReport = document.getElementById("siteReport");
    employeeSelect = document.getElementById("employeeSelect");

    console.log('USER_ROLE =', window.USER_ROLE);

    // Ładowanie danych do selectów
    if (typeof loadEmployeesForManual === 'function') {
        loadEmployeesForManual();
    }

    if (typeof loadSitesForManual === 'function') {
        loadSitesForManual();
    }
    
    if (typeof loadEmployeesForAbsence === 'function') {
        loadEmployeesForAbsence();
    }
    
    if (typeof loadFuelMachines === 'function') {
        loadFuelMachines();
    }

    if (typeof loadMachinesForManualFuel === 'function') {
        loadMachinesForManualFuel();
    }
    
    if (typeof loadMachinesForReport === 'function') {
        loadMachinesForReport();
    }
    
    if (typeof loadEmployees === 'function') {
        loadEmployees();
    }
    
    if (typeof loadSitesForReport === 'function') {
        loadSitesForReport();
    }

    applyRoleVisibility();

    // Sesje do akceptacji (tylko role 2 i 9) – odświeżanie cykliczne
    if (typeof loadPendingSessions === 'function') {
        loadPendingSessions();
        // prawie w czasie rzeczywistym: odświeżaj listę co 10 sekund
        setInterval(loadPendingSessions, 10000);
    }

    // Inicjalizacja mapy geofencingu (tylko jeśli sekcja istnieje)
    if (typeof initGeoMap === 'function') {
        initGeoMap();
    }
});

