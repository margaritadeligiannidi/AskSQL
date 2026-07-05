
/* LOAD SAVED CONNECTIONS */
async function loadConnections() {
     // API FETCH //
    const data = await apiFetch("../api/get_connections.php");
    console.log("DATA:", data);

    if (!data) return;

    if (data.error) {
        showMessage(data.error, "danger");
        return;
    }

    const box = document.getElementById("connectionsBox");
    box.innerHTML = "";

    if (!data.connections || data.connections.length === 0) {
        box.innerHTML = `
   <div class="text-center py-5 text-muted">
    <i class="bi bi-database-x fs-1 d-block mb-3"></i>
    <h5>No saved connections</h5>
    <p class="mb-0">
        Create your first database connection.
    </p>
</div>
`;
        return;
    }

   

    data.connections.forEach(conn => {

        const col = document.createElement("div");
        col.className = "col-12 col-sm-6 col-lg-4";

        const dbIcon =
             conn.db_type === "mysql"
              ? "../images/mysql.png"
              : "../images/postgres.png";

    
      col.innerHTML = `
<div class="card h-100 connection-card">

    <div class="card-body d-flex flex-column">

        <div class="connection-header">

            <div class="connection-icon">
              <img src="${dbIcon}" alt="${conn.db_type}">
            </div>

            <div class="flex-grow-1">

                <h5 class="card-title mb-1">
                    ${conn.name || "Unnamed Connection"}
                </h5>

                <span class="badge bg-primary-subtle text-primary">
                    ${conn.db_type}
                </span>

            </div>

        </div>

        <hr>

        <div class="connection-details">

            <div class="detail-row">
                <i class="bi bi-globe"></i>
                <div>
                    <small>Host</small>
                    <strong>${conn.host}:${conn.port}</strong>
                </div>
            </div>

            <div class="detail-row">
                <i class="bi bi-person-fill"></i>
                <div>
                    <small>User</small>
                    <strong>${conn.db_username}</strong>
                </div>
            </div>

            <div class="detail-row">
                <i class="bi bi-hdd-stack-fill"></i>
                <div>
                    <small>Database</small>
                    <strong>${conn.db_name}</strong>
                </div>
            </div>

        </div>

       <div class="mt-auto pt-4">
<div class="mt-auto pt-3">

    <div class="action-links">

        <span class="action-link connect-btn">
            <i class="bi bi-plug-fill"></i>
            Connect
        </span>

        <span class="action-link rename-btn">
            <i class="bi bi-pencil-square"></i>
            Rename
        </span>

        <span class="action-link delete-btn">
            <i class="bi bi-trash"></i>
            Delete
        </span>

    </div>

</div>

</div>

    </div>

</div>
`;
    
        col.querySelector(".connect-btn").onclick = () => reconnect(conn.id);
        col.querySelector(".rename-btn").onclick = () => rename(conn);
        col.querySelector(".delete-btn").onclick = () => delete_connection(conn);
    
        box.appendChild(col);
    });
}

/* LOAD USERNAME FOR NAVBAR */
async function loadUsername() {
    const data = await apiFetch("../api/profile.php", {
        method: "GET",
        headers: { "Content-Type": "application/json" }
    });

     console.log("PROFILE RESPONSE:", data);

    if (data && data.username) {
        document.getElementById("navbarUsername").innerText = data.username;
    }
}

/* RECONNECT  */
async function reconnect(connectionId) {

   const data = await apiFetch("../api/connect.php", {
    method: "POST",
    body: {
        connection_id: connectionId
    }
});

    console.log("RECONNECT RESPONSE:", data);
    if (data?.success) {
        showMessage("Connected!", "success");
    
        if (data.connection_id) {
            setConnectionId(data.connection_id);
        }

        setTimeout(() => {
            window.location.href = "dashboard.html";
        }, 800);
    } else {
       showMessage(data?.error || "Connection failed", "danger");
    }
}

/*  DELETE */
function delete_connection(conn) {

    document.getElementById("modalTitle").innerText = "Delete Connection";
    document.getElementById("modalBody").innerText = `Delete "${conn.name}"?`;
    document.getElementById("renameBox").classList.add("d-none");

    confirmCallback = async () => {
         // API FETCH //
       const data = await apiFetch("../api/delete_connection.php", {
    method: "POST",
    body: {
        id: conn.id
    }
});

        if (data?.success) {
            showMessage("Delete successful", "success");
            loadConnections();
        } else {
            showMessage(data?.error || "Delete failed", "danger");
        }
    };

    modal.show();
}

/*RENAME */
function rename(conn) {

    document.getElementById("modalTitle").innerText = "Rename Connection";
    document.getElementById("modalBody").innerText = "Enter new name:";
    document.getElementById("renameBox").classList.remove("d-none");

    const input = document.getElementById("renameInput");
    input.value = conn.name || "";

    confirmCallback = async () => {

        const newName = input.value.trim();
        if (!newName) {
            showMessage("Name cannot be empty", "danger");
            return;
        }

         // API FETCH //
      const data = await apiFetch("../api/rename_connection.php", {
    method: "POST",
    body: {
        name: newName,
        id: conn.id
    }
});

        if (data?.success) {
            showMessage("Rename successful", "success");
            loadConnections();
        } else {
            showMessage(data?.error || "Rename failed", "danger");
        }
    };

    modal.show();
}

function showMessage(message, type = "success") {
    const box = document.getElementById("messageBox");

    box.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // auto hide μετά από 3 sec
    setTimeout(() => {
        box.innerHTML = "";
    }, 3000);
}


let modal;
let confirmCallback = null;

document.addEventListener("DOMContentLoaded", () => {
    modal = new bootstrap.Modal(document.getElementById("actionModal"));

    const btn = document.getElementById("modalConfirmBtn");

document.getElementById("modalConfirmBtn").onclick = async () => {
    if (!confirmCallback) return;

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Working...`;

    await confirmCallback();

    btn.disabled = false;
    btn.innerHTML = `<i class="bi bi-check-circle me-1"></i> Confirm`;

    modal.hide();
};

    loadConnections();
    loadUsername();
});


/*  INIT */
// LOGOUT handled by auth.js

 // API FETCH //
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });


  function openSUSModal() { 

    window.open("https://docs.google.com/forms/d/e/1FAIpQLScN5oNctxkFbEYmKcboImZsmEgrApA8KCT_VX4_fRTDN48WMw/viewform?usp=publish-editor", "_blank"); 

} 


//PROFILE
function openProfile() { 
    window.location.href = "../html/profile.html"; 
} 
