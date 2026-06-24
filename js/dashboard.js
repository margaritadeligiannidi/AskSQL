/* STATE  */ 
let hasNextPage = false;
let selectedDatabase = null; 
let schemaDDL = ""; 
let activeDBElement = null; 
let activeTableElement = null; 
// PAGINATION
let currentPage = 1;
const PAGE_SIZE = 50;
// LAST EXECUTED QUERY
let lastQuery = "";
let lastMode = "";
// NEW
let lastGeneratedSQL = "";

document.addEventListener("DOMContentLoaded", () => {

    const token = getAuthToken();

    if (!token) {
        window.location.href = "../html/login.html";
        return;
    }

    loadUsername();
    loadDatabases();

    const modeEl = document.getElementById("mode");

    if (modeEl) {

        modeEl.addEventListener("change", () => {

            updatePlaceholder();

            document.getElementById("queryInput").value = "";
            document.getElementById("generatedSQL").textContent = "";

            clearUI();
            loadHistory();
        });
    }

    document.getElementById("mode").value = "sql";
    updatePlaceholder();
});
 
/*  PLACEHOLDER */ 
function updatePlaceholder() { 

    const mode = document.getElementById("mode").value; 
    const input = document.getElementById("queryInput"); 
    const btn = document.getElementById("runBtn"); 
    const generated = document.getElementById("generatedSQL");
    const title = document.getElementById("generatedTitle");
    const editBtn = document.getElementById("editGeneratedBtn");
    const runBtnGenerated = document.getElementById("runGeneratedBtn");
    const voiceBtn = document.getElementById("voiceBtn");
    const voiceLang = document.getElementById("voiceLang");
    const modelProvider =
    document.getElementById("modelProvider");
    const wrapper = document.querySelector(".query-input-wrapper");

 if (mode === "sql") {

    input.placeholder = "Write SQL query (e.g. SELECT * FROM users)";

    btn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Run';

    generated.classList.add("d-none");
    generated.textContent = "";
    lastGeneratedSQL = "";
    editBtn.classList.add("d-none");
    runBtnGenerated.classList.add("d-none");
    modelProvider.classList.add("d-none");

    if (title) title.style.display = "none";

    // HIDE VOICE
    voiceBtn.classList.add("d-none");
    voiceLang.classList.add("d-none");

    // FULL WIDTH TEXTAREA
    wrapper.classList.add("sql-mode");

} else {

    input.placeholder = "Ask a question (e.g. show all customers)";

    btn.innerHTML = '<i class="bi bi-stars me-1"></i> Ask AI';

    generated.classList.remove("d-none");

    editBtn.classList.remove("d-none");
    runBtnGenerated.classList.remove("d-none");
    modelProvider.classList.remove("d-none");

    if (title) title.style.display = "block";

    // SHOW VOICE
    voiceBtn.classList.remove("d-none");
    voiceLang.classList.remove("d-none");

    // RESTORE LAYOUT
    wrapper.classList.remove("sql-mode");
}
}




 
/* DANGEROUS QUERY  */ 
function isDangerousQuery(query) { 

    const q = query.toLowerCase().trim(); 

    return [ 

        /\bdrop\b/, 

        /\bdelete\b/, 

        /\btruncate\b/, 

        /\balter\b/, 

        /\bupdate\b/, 

        /\binsert\b/ 

    ].some(p => p.test(q)); 

} 

 
function isVeryDangerous(query) { 

    const q = query.toLowerCase().trim(); 

    if (/delete\s+from\s+\w+\s*;?$/.test(q)) return true; 
    if (/update\s+\w+\s+set\s+.+$/i.test(q) && !q.includes("where")) return true; 

    return false; 
} 


/*  UI RESET  */ 
function clearUI() { 
    document.querySelector("#resultsTable thead").innerHTML = "";
    document.querySelector("#resultsTable tbody").innerHTML = "";

    document.getElementById("generatedSQL").textContent = "";
    document.getElementById("queryInput").value = "";

    lastQuery = "";
    lastGeneratedSQL = "";
    currentPage = 1;
    hasNextPage = false; // <-- ΠΡΟΣΘΗΚΗ
} 



/*  LOAD USERNAME FOR NAVBAR  */ 
async function loadUsername() {
    const data = await apiFetch("../api/profile.php", {
        method: "GET",
        headers: { "Content-Type": "application/json" }
    });

    if (data && data.username) {
        document.getElementById("navbarUsername").innerText = data.username;
    }
}

/*  LOAD DATABASES  */ 
async function loadDatabases() { 
//-----------------------API FETCH------------------------------------
    const connectionId = getConnectionId();

    const data = await apiFetch("../api/get_databases.php");

    if (!data?.success) return; 

    const list = document.getElementById("dbList"); 
    list.innerHTML = ""; 

    data.databases.forEach(db => { 
        const li = document.createElement("li"); 

        li.className = "list-group-item p-1"; 

        const btn = document.createElement("button"); 
        btn.className = "btn btn-sm w-100 text-start text-truncate"; 
        btn.innerHTML = `<strong>${db}</strong>`; 

        const subList = document.createElement("ul"); 
        subList.className = "list-group mt-2"; 
        subList.style.display = "none"; 

        btn.addEventListener("click", async (e) => { 

            e.stopPropagation(); 

            if (activeDBElement && activeDBElement !== btn) { 

                activeDBElement.classList.remove("active"); 
                activeDBElement.parentElement.querySelector("ul").style.display = "none"; 
            } 

            const isOpen = subList.style.display === "block"; 

            if (!isOpen) { 

                subList.style.display = "block";
                  subList.innerHTML = `<li class="list-group-item text-center text-secondary">
                                       <span class="spinner-border spinner-border-sm me-2"
                                       role="status"></span>
                                       Loading tables
                                      </li>
                                    `;

await loadTablesInto(db, subList);

                selectedDatabase = db; 
                activeDBElement = btn; 

                btn.classList.add("active"); 

                clearUI(); 
                await getSchema(); 
                await loadHistory(); 

            } else { 
                subList.style.display = "none"; 
                btn.classList.remove("active"); 
            } 

        }); 

        li.appendChild(btn); 
        li.appendChild(subList); 
        list.appendChild(li); 
    }); 
} 


/*  LOAD TABLES  */ 
async function loadTablesInto(dbName, container) { 
                              //API CALL //
    const connectionId = getConnectionId();

const data = await apiFetch(
    `../api/get_tables.php?connection_id=${connectionId}&database=${encodeURIComponent(dbName)}`
);

    if (!data?.success) return; 

    container.innerHTML = ""; 

    if (!data.tables.length) { 
       container.innerHTML = "<li class='list-group-item'>No tables</li>"; 
        return; 
    } 

 
    data.tables.forEach(table => { 

        const li = document.createElement("li"); 
        li.className = "list-group-item d-flex align-items-center gap-2 small";
         const name = document.createElement("span"); 
         name.textContent = table; 


        const btn = document.createElement("button"); 
        btn.className = "btn btn-sm btn-outline-primary";  
        btn.innerHTML = '<i class="bi bi-grid"></i>';


        btn.onclick = async (e) => { 

            e.stopPropagation(); 

            await loadColumns(dbName, table); 

            openColumnsPanel(table); 

        }; 

        li.onclick = () => { 

            if (activeTableElement) activeTableElement.classList.remove("active"); 

            li.classList.add("active"); 

            activeTableElement = li; 

        }; 

        li.appendChild(btn);
        li.appendChild(name); 
        container.appendChild(li); 
    }); 
} 

 

/* GET SCHEMA  */ 
async function getSchema() { 

    if (!selectedDatabase) return; 
                     // API FETCH //
    const connectionId = getConnectionId();
    const data = await apiFetch(
      `../api/get_schema.php?connection_id=${connectionId}&database=${encodeURIComponent(selectedDatabase)}`
     );

    schemaDDL = data?.success ? data.schema : ""; 

    console.log("SCHEMA DUMP:"); 
    console.log(schemaDDL); 

    if (schemaDDL) { 

        console.group("LLM SCHEMA INPUT"); 
        console.log("Schema:"); 
        console.log(schemaDDL); 

        const tokens = Math.ceil(schemaDDL.length / 4); 
        console.log("Schema length:", schemaDDL.length); 
        console.log("Estimated tokens:", tokens); 
        console.groupEnd(); 

    } 
} 


/* HANDLE QUERY  */ 
async function handleQuery() { 

    const input = document.getElementById("queryInput").value.trim(); 
    const mode = document.getElementById("mode").value; 
    const provider = document.getElementById("modelProvider")?.value || "openai";

     if (!input) { 
         showErrorModal("Write something first !", "validation"); 
         return; 
      } 

      if (!selectedDatabase) { 
          showErrorModal("Select a database first !", "validation"); 
         return; 
     } 

    const runBtn = document.getElementById("runBtn");

        if (mode === "nl") {
          runBtn.innerHTML = ` <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Thinking...`;
        } else {
           runBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"
                                 role="status"></span>
                                 Running`;
        }

    runBtn.disabled = true;

    // CACHE CHECK 
                    //API CALL/
 const cache = await apiFetch("../api/check_cache.php", {

    method: "POST",

    headers: { "Content-Type": "application/json" },

body: {
    question: input,
    sql: mode === "sql" ? input : "",
    mode: mode,
    database: selectedDatabase,
    connection_id: getConnectionId()
}
    }); 

 
    console.log("CACHE RESPONSE:", cache); 
    
    if (cache?.found) {
        console.log("FROM CACHE - NO LLM");
        document.getElementById("generatedSQL").textContent = cache.sql;
        lastGeneratedSQL = cache.sql;
        // SQL mode -> execute directly
        if (mode === "sql") {
            handleDangerousExecution(cache.sql, input);
        } else {
            // NL mode -> show only
            runBtn.innerHTML = '<i class="bi bi-stars me-1"></i> Ask AI';
            runBtn.disabled = false;
        }
        return;
    }

       currentPage = 1;
       hasNextPage = false;
       lastQuery = input;
       lastMode = mode;
            // αν δεν βρεθεί 
          if (mode === "sql") { 
            handleDangerousExecution(input); 
           } else { 
              runNL(input); 
              console.log("CALLING LLM..."); 
            } 
} 

 

/*  RUN SQL  */ 
async function runSQL(
    sql,
    originalQuestion = sql,
    confirmed = false,
    saveHistory = true
) {

    const runBtn = document.getElementById("runBtn");

    try {

        // AUTO PAGINATION
        if (
            /^\s*select\b/i.test(sql) &&
            !/limit\b/i.test(sql)
        ) {

            const offset =
                (currentPage - 1) * PAGE_SIZE;

            sql = sql.replace(/;?\s*$/, "") + ` LIMIT ${PAGE_SIZE + 1} OFFSET ${offset}`;
        }

        // EXECUTE QUERY
        const data = await apiFetch(
            "../api/execute_query.php",
            {

                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

 body: {
    sql,
    database: selectedDatabase,
    confirmed
}
            }
        );

        if (
            data?.error === "CONFIRM_REQUIRED"
            && !confirmed
        ) {

            handleDangerousExecution(
                sql,
                originalQuestion
            );

            return;
        }

        if (!data?.success) {

            showErrorModal(
                data?.error || "Query failed"
            );

        } else {

if (data?.success && data.data) {

    hasNextPage =
        data.data.length > PAGE_SIZE;

    if (hasNextPage) {
        data.data =
            data.data.slice(0, PAGE_SIZE);
    }
}

            renderResults(data);

            // SAVE ONLY ON FIRST EXECUTION
            if (saveHistory) {

                await apiFetch(
                    "../api/save_query.php",
                    {

                        method: "POST",

                        headers: {
                            "Content-Type":
                                "application/json"
                        },

body: {
    question:
        lastMode === "nl"
            ? lastQuery
            : "",

    sql:
        lastMode === "nl"
            ? lastGeneratedSQL
            : originalQuestion,

    mode: lastMode,

    database: selectedDatabase,

    connection_id: getConnectionId()
}
                    }
                );

                loadHistory();
            }
        }

    } finally {

        const mode =
            document.getElementById("mode").value;

        runBtn.innerHTML =
            mode === "sql"
                ? '<i class="bi bi-play-fill me-1"></i> Run'
                : '<i class="bi bi-stars me-1"></i> Ask AI';

        runBtn.disabled = false;

        const runBtnGenerated =
            document.getElementById(
                "runGeneratedBtn"
            );

        if (runBtnGenerated) {

            runBtnGenerated.innerHTML =
                '<i class="bi bi-play-fill"></i>';

            runBtnGenerated.disabled = false;
        }
    }
}
 
/* RUN NL  */ 
async function runNL(question) {

    if (!schemaDDL || schemaDDL.length < 50) {

        const runBtn = document.getElementById("runBtn");

        runBtn.innerHTML =
            '<i class="bi bi-stars me-1"></i> Ask AI';

        runBtn.disabled = false;

        showErrorModal("Schema not loaded yet", "validation");

        return;
    }

    const output = document.getElementById("generatedSQL");

    output.innerHTML = `
        <div class="d-flex align-items-center text-secondary">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            <span>Generating SQL...</span>
        </div>
    `;

    try {

        const provider =
            document.getElementById("modelProvider")?.value || "openai";

        console.log("SELECTED PROVIDER:", provider);

        const data = await apiFetch("../api/nl2sql.php", {

            method: "POST",

            headers: {
                "Content-Type": "application/json"
            },

            body: {
                question,
                ddl: schemaDDL,
                database: selectedDatabase,
                provider
            }
        });

        if (data?.success) {

            output.textContent = data.sql;
            lastGeneratedSQL = data.sql;

        } else {

            output.textContent = "Error generating SQL";
        }

    } catch (e) {

        console.error(e);
        output.textContent = "Server error";

    } finally {

        const runBtn = document.getElementById("runBtn");

        runBtn.innerHTML =
            '<i class="bi bi-stars me-1"></i> Ask AI';

        runBtn.disabled = false;
    }
}

 

/* EDIT GENERATED SQL */
function editGeneratedSQL() {

    const output = document.getElementById("generatedSQL");
    const editBtn = document.getElementById("editGeneratedBtn");

    const isEditing =
        output.getAttribute("contenteditable") === "true";

    // ENABLE EDIT
    if (!isEditing) {

        output.setAttribute("contenteditable", "true");

        output.focus();

        output.classList.add("border", "border-primary");

        editBtn.innerHTML =
            '<i class="bi bi-check-lg"></i>';

        editBtn.classList.remove("btn-outline-secondary");

        editBtn.classList.add("btn-success");

    } else {

        // SAVE MODE
        output.setAttribute("contenteditable", "false");

        output.classList.remove("border", "border-primary");

        editBtn.innerHTML =
            '<i class="bi bi-pencil"></i>';

        editBtn.classList.remove("btn-success");

        editBtn.classList.add("btn-outline-secondary");
    }
}



/* RUN GENERATED SQL */
function runGeneratedSQL() {

    const sql = document.getElementById("generatedSQL").textContent.trim();

    if (!sql) {
        showErrorModal("No generated SQL found", "validation");
        return;
    }

    const runBtnGenerated =
        document.getElementById("runGeneratedBtn");

    // SPINNER
    runBtnGenerated.innerHTML = `
        <span class="spinner-border spinner-border-sm"
              role="status"></span>
    `;

    runBtnGenerated.disabled = true;

    currentPage = 1;
    lastGeneratedSQL = sql;

    handleDangerousExecution(sql);
}


/* RENDER RESULTS */ 
function renderResults(data) { 

    const thead = document.querySelector("#resultsTable thead"); 
    const tbody = document.querySelector("#resultsTable tbody"); 
 
    thead.innerHTML = ""; 
    tbody.innerHTML = ""; 

    if (!data?.success) return; 

    if (!data.data || !data.data.length) { 
        tbody.innerHTML = "<tr><td colspan='100%'>Query executed successfully</td></tr>";
        return; 
    } 

    const headers = Object.keys(data.data[0]); 
    const trHead = document.createElement("tr"); 

    headers.forEach(h => { 

        const th = document.createElement("th"); 

        th.textContent = h; 

        trHead.appendChild(th); 

    }); 

 

    thead.appendChild(trHead); 

 

    data.data.forEach(row => { 

        const tr = document.createElement("tr"); 
 

        headers.forEach(h => { 

            const td = document.createElement("td"); 

            const value = row[h]; 

         
            // NULL handling 
            if (value === null) { 
                td.innerHTML = "<span class='cell-null'>NULL</span>"; 
            } else { 
                td.textContent = value; 
                td.classList.add(getCellClass(value)); 
            } 

         
            tr.appendChild(td); 
        }); 

        tbody.appendChild(tr); 

    }); 
    updatePagination(data.data.length);

} 

 

function getCellClass(value) { 

    if (value === null) return "cell-null"; 

    // boolean 
    if (value === true || value === false) { 
        return "cell-boolean"; 
    } 

    if (typeof value === "string") { 
        const v = value.trim(); 

        // DATE 
        if (/^\d{4}-\d{2}-\d{2}/.test(v)) { 
            return "cell-date"; 
        } 

        // NUMBER (μόνο αν είναι καθαρός αριθμός) 
        if (/^-?\d+(\.\d+)?$/.test(v)) { 
            return "cell-number"; 
        } 

        return "cell-string"; 
    } 

    // fallback 
    return "cell-string"; 
} 

 

/* DANGER MODAL  */ 
function handleDangerousExecution(query, originalQuestion = query) { 

    if (!isDangerousQuery(query)) { 
        runSQL(query, originalQuestion); 
        return; 
    } 

 
    const modal = new bootstrap.Modal(document.getElementById("dangerModal")); 
    const text = document.getElementById("dangerText"); 

    let message = "This query may modify or delete data."; 
     if (isVeryDangerous(query)) { 
          message = "VERY DANGEROUS!\nThis query may affect ALL rows."; 
     } 

    if (isVeryDangerous(query)) { 
        message = "VERY DANGEROUS!\nThis query may affect ALL rows."; 
    } 

 

    text.innerText = message; 

    modal.show(); 

 

    const btn = document.getElementById("confirmDangerBtn"); 
    btn.replaceWith(btn.cloneNode(true)); 
    const newBtn = document.getElementById("confirmDangerBtn"); 
    newBtn.onclick = () => { 

        modal.hide(); 
        runSQL(query, originalQuestion, true); 

    }; 
} 

 
/* LOAD COLUMNS  */ 
async function loadColumns(dbName, tableName) { 
                                 //API CALL

    const connectionId = getConnectionId();

const data = await apiFetch(
    `../api/get_columns.php?connection_id=${connectionId}&database=${encodeURIComponent(dbName)}&table=${encodeURIComponent(tableName)}`
);

    if (!data?.success) return; 

    const tbody = document.getElementById("columnsTableBody"); 

    if (!tbody) return; 

    tbody.innerHTML = ""; 


    data.columns.forEach(col => { 

        const tr = document.createElement("tr"); 

     

        tr.innerHTML = ` 

            <td class="column-name">${col.COLUMN_NAME}</td> 

            <td>${col.COLUMN_TYPE}</td> 

            <td>${col.IS_NULLABLE}</td> 

            <td>${col.COLUMN_KEY || ""}</td> 

            <td>${col.COLUMN_DEFAULT || ""}</td> 

            <td>${col.EXTRA || ""}</td> 

        `; 

     

        tr.querySelector(".column-name").onclick = () => { 

            const input = document.getElementById("queryInput"); 

            input.value += col.COLUMN_NAME + " "; 

            input.focus(); 

        }; 

     

        tbody.appendChild(tr); 

    }); 

} 

 

 

function openColumnsPanel(tableName) { 

    document.getElementById("columnsTitle").innerText = "Table: " + tableName; 

    document.getElementById("columnsPanel").classList.add("open"); 

    document.body.classList.add("panel-open"); 

} 

 

function closeColumnsPanel() { 

    document.getElementById("columnsPanel").classList.remove("open"); 

    document.body.classList.remove("panel-open");

} 

 

/*  UTILS  */ 

function exportCSV() { 

    let query = document.getElementById("queryInput").value; 
    let sqlQuery = document.getElementById("generatedSQL").innerText; 
    let mode = document.getElementById("mode").value; 

 

    if (mode === "nl" && sqlQuery.trim()) { 
        query = sqlQuery; 
    } 
 

    if (!query.trim()) { 
        showErrorModal("No query to export !", "validation"); 
        return; 
    } 
                        //API CALL
const token = getAuthToken();

window.open(
    "../api/export.php?sql="
    + encodeURIComponent(query)
    + "&database="
    + encodeURIComponent(selectedDatabase)
    + "&connection_id="
    + encodeURIComponent(getConnectionId())
    + "&token="
    + encodeURIComponent(token)
);
} 

 
// LOGOUT
function logout() {

    clearAuthToken();
    clearConnectionId();

    sessionStorage.clear();

    window.location.href = "../html/login.html";
}

// BACK TO CONNECTIONS - Clear dashboard state and navigate
function backToConnections() {

    // Clear dashboard state variables
    selectedDatabase = null;
    schemaDDL = "";
    activeDBElement = null;
    activeTableElement = null;
    currentPage = 1;
    lastQuery = "";
    lastMode = "";
    lastGeneratedSQL = "";

    // Clear any UI elements that might hold state
    const dbList = document.getElementById("dbList");
    if (dbList) dbList.innerHTML = "";

    const queryInput = document.getElementById("queryInput");
    if (queryInput) queryInput.value = "";

    clearUI();

    // Navigate to connections page (token and connection_id remain in localStorage)
    window.location.href = "../html/connections.html";
}

//PROFILE
function openProfile() { 
    window.location.href = "../html/profile.html"; 
} 

 

 

/* MODALS */ 
function openModal() { 

    const modalEl = document.getElementById("columnsModal"); 
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl); 
    modal.show(); 

} 


 
//ERROR
function showErrorModal(message, type = "error") { 

    const modalEl = document.getElementById("errorModal"); 
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl); 
    const title = document.getElementById("errorTitle"); 

 

    // reset classes 
    title.classList.remove("text-danger", "text-warning"); 

 

    if (type === "validation") { 

        title.textContent = "Warning"; 

        title.classList.add("text-warning"); 

    } else { 

        title.textContent = "Query Error"; 

        title.classList.add("text-danger"); 

    } 

 

    document.getElementById("errorText").textContent = message; 

 

    modal.show(); 

} 

 

 

 

/* VOICE INPUT  */ 

let recognition; 
let isListening = false; 


function startVoice() { 

    if (!('webkitSpeechRecognition' in window)) { 

        showErrorModal("Speech recognition not supported in this browser"); 

        return; 

    } 

 

    if (isListening) { 

        recognition.stop(); 

        return; 

    } 

 

    recognition = new webkitSpeechRecognition(); 

    recognition.lang = document.getElementById("voiceLang").value;

    recognition.interimResults = false; 

 

    const btn = document.getElementById("voiceBtn"); 

 

    recognition.onstart = () => { 

        isListening = true; 

        btn.classList.add("listening"); 
           // Clear previous text
        document.getElementById("queryInput").value = "";

    }; 

 

    recognition.onend = () => { 

        isListening = false; 

        btn.classList.remove("listening"); 

    }; 

 

    recognition.onresult = (event) => { 

        const text = event.results[0][0].transcript; 

        const input = document.getElementById("queryInput"); 
        input.value = text ; 

 

        //  auto-run 

        if (document.getElementById("mode").value === "nl") { 

            handleQuery(); 

        } 

    }; 

 

    recognition.onerror = (e) => { 

        console.error(e); 

        showErrorModal("Voice recognition error"); 

    }; 

    recognition.start(); 

} 

 

 
/* NORMALIZE SQL */
function normalizeSQL(sql) {

    return sql
        .toLowerCase()
        .replace(/;$/, "")
        .replace(/\s+/g, " ")
        .trim();
}
 

async function loadHistory() { 

    if (!selectedDatabase) return; 
                                     //API CALL
   const connectionId = getConnectionId();

const data = await apiFetch(
    "../api/get_history.php",
    {
        method: "POST",

        headers: {
            "Content-Type": "application/json"
        },

        body: {
            connection_id: connectionId,
            database: selectedDatabase
        }
    }
);

    if (!data?.success) return; 

 

    const list = document.getElementById("historyList"); 

    list.innerHTML = ""; 

 

    const mode = document.getElementById("mode").value; 

 

    const seen = new Set(); // prevent duplicates 

 

    data.history.forEach(item => { 

        let text; 

 

        if (mode === "nl") { 

            if (item.mode !== "nl") return;  

            text = item.question; 

        } else { 

            if (item.mode !== "sql") return; 

            text = item.sql_query; 

        } 

 

      const normalized =
    mode === "sql"
        ? normalizeSQL(text)
        : text.trim().toLowerCase();

if (seen.has(normalized)) return;

seen.add(normalized);

 

        const li = document.createElement("li"); 

        li.className = "list-group-item small"; 

        li.innerHTML = `<div>${text}</div>`; 

 
        li.onclick = () => {

            const input = document.getElementById("queryInput");
            const generated = document.getElementById("generatedSQL");
        
            if (mode === "nl") {
        
                // δείξε το φυσικό ερώτημα
                input.value = item.question || "";
        
                // δείξε και το generated SQL
                generated.textContent = item.sql_query || "";
        
            } else {
        
                // SQL mode
                input.value = item.sql_query || "";
        
                // καθάρισε το generated box
                generated.textContent = "";
        
            }
        };

        list.appendChild(li); 

    }); 

} 

 

function openSUSModal() { 

    window.open("https://docs.google.com/forms/d/e/1FAIpQLScN5oNctxkFbEYmKcboImZsmEgrApA8KCT_VX4_fRTDN48WMw/viewform?usp=publish-editor", "_blank"); 

} 


//TOOLTIP
document.addEventListener("DOMContentLoaded", function () {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});



/* DELETE HISTORY */
function confirmDeleteHistory() {

    if (!selectedDatabase) {
        showErrorModal("Select a database first", "validation");
        return;
    }

    const modal = new bootstrap.Modal(
        document.getElementById("deleteHistoryModal")
    );

    modal.show();

    const btn = document.getElementById("confirmDeleteHistoryBtn");

    // prevent duplicate listeners
    btn.replaceWith(btn.cloneNode(true));
    const newBtn = document.getElementById("confirmDeleteHistoryBtn");

    newBtn.onclick = async () => {
        const data = await apiFetch(
            //API CALL
            "../api/delete_history.php",
            {
                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

body: {
    database: selectedDatabase,
    connection_id: getConnectionId()
}
            }
        );

        modal.hide();

        if (!data?.success) {
            showErrorModal(
                data?.error || "Failed to delete history"
            );
            return;
        }
        // refresh history list
        loadHistory();
    };
}


document.getElementById("prevPageBtn").onclick = () => {

    if (currentPage <= 1) return;

    currentPage--;

    rerunLastQuery();
};

document.getElementById("nextPageBtn").onclick = () => {

    currentPage++;

    rerunLastQuery();
};


function rerunLastQuery() {

    if (lastMode === "sql") {

        runSQL(
            lastQuery,
            lastQuery,
            false,
            false
        );

    } else {

        runSQL(
            lastGeneratedSQL,
            lastGeneratedSQL,
            false,
            false
        );
    }
}

function updatePagination(rowCount) {

    const box = document.getElementById("paginationBox");

   if (currentPage === 1 && rowCount === 0) {
    box.classList.add("d-none");
    return;
}

    box.classList.remove("d-none");
    document.getElementById("pageIndicator").innerText =currentPage;
    document.getElementById("prevPageBtn").disabled = currentPage === 1;
    document.getElementById("nextPageBtn").disabled = !hasNextPage;
}