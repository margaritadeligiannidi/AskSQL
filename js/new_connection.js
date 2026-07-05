/* ELEMENTS */
const dbType = document.getElementById("dbType");
const sshFields = document.getElementById("sshFields");
const databaseLabel = document.getElementById("databaseLabel");

/*  TOGGLE SSH */
function toggleSSH() {
    if (!dbType || !sshFields) return;

    const isSSH = dbType.value.includes("ssh");

    sshFields.style.display = isSSH ? "block" : "none";

    document.querySelectorAll("#sshFields input").forEach(input => {
      
              input.disabled = !isSSH;

              // SSH Port optional
             if (input.name === "ssh_port") {
                 input.required = false;
             } else {
                 input.required = isSSH;
             }

              /* default SSH port */
             if (isSSH && input.name === "ssh_port" && !input.value) {
                input.value = "22";
             }

              /* clear fields when SSH disabled */
             if (!isSSH) {
                 if (input.name !== "ssh_port") {
                   input.value = "";
                }
             }
    });
}

/* AUTO PORT  */
function setDefaultPort() {
    const portInput = document.getElementById("dbPort");
    if (!dbType || !portInput) return;

    const type = dbType.value;

    if (type.includes("mysql")) {
        portInput.value = "3306";
    } else if (type.includes("pgsql")) {
        portInput.value = "5432";
    }
}



function toggleDatabaseRequired() {

    if (!dbType || !databaseLabel) return;

    const isPostgres = dbType.value.includes("pgsql");

    databaseLabel.innerHTML = isPostgres
        ? 'Database <span class="text-danger">*</span>'
        : 'Database';
}



 // message //
function showMessage(message, type = "success") {
    const box = document.getElementById("messageBox");

    box.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    window.scrollTo({ top: 0, behavior: "smooth" });

    setTimeout(() => {
        box.innerHTML = "";
    }, 3000);
}

/* LOAD USERNAME FOR NAVBAR */
async function loadUsername() {
    const data = await apiFetch("../api/profile.php", {
        method: "GET",
        headers: { "Content-Type": "application/json" }
    });

    if (data && data.username) {
        document.getElementById("navbarUsername").innerText = data.username;
    }
}


/*  FORM SUBMIT */
const form = document.getElementById("connectForm");

if (form) {
    form.addEventListener("submit", async function(e) {
        e.preventDefault();
    
        const btn = document.getElementById("connectBtn");
    
        btn.disabled = true;
        btn.textContent = "Connecting...";
    
        try {
            const formData = new FormData(this);
            const host = formData.get("host");
            const user = formData.get("username");
            const type = formData.get("type");
            const database = formData.get("database")?.trim();
    
            if (!host || !user) {

                showMessage("Please fill all required fields", "danger");
            
                btn.disabled = false;
                btn.textContent = "Connect to Database";
            
                return;
            }
            
            /* PostgreSQL requires database */
            if (type.includes("pgsql") && !database) {
            
                showMessage(
                    "Database name is required for PostgreSQL",
                    "danger"
                );
            
                btn.disabled = false;
                btn.textContent = "Connect to Database";
            
                return;
            }
            
            // Use raw fetch instead of apiFetch to avoid automatic connection_id attachment
            // This ensures it's treated as a NEW connection, not a reconnection
            const token = getAuthToken();
            const fetchOptions = {
                method: "POST",
                headers: {
                    'Authorization': `Bearer ${token}`
                },
                body: formData
            };

            const response = await fetch("../api/connect.php", fetchOptions);
            const data = await response.json();
    
            if (data?.success) {
                showMessage("Connected successfully!", "success");

                // store active connection id for the frontend
                if (data.connection_id) {
                    setConnectionId(data.connection_id);
                }

                setTimeout(() => {
                    window.location.href = "dashboard.html";
                }, 800);
            } else {
                showMessage(data?.error || "Connection failed", "danger");
            }
    
        } catch (err) {
            console.error(err);
            showMessage("Unexpected error", "danger");
        } finally {
            btn.disabled = false;
            btn.textContent = "Connect to Database";
        }
    });
}



document.addEventListener("click", function(e) {
    if (!e.target.classList.contains("toggle-password")) return;

    const container = e.target.closest(".position-relative");
    const input = container.querySelector(".password-field");

    const isHidden = input.type === "password";
    input.type = isHidden ? "text" : "password";

    e.target.classList.toggle("bi-eye");
    e.target.classList.toggle("bi-eye-slash");
});

/*  INIT */
document.addEventListener("DOMContentLoaded", () => {
    toggleSSH();
    setDefaultPort();
    toggleDatabaseRequired();
    loadUsername();
});

if (dbType) {
    dbType.addEventListener("change", () => {
        toggleSSH();
        setDefaultPort();
        toggleDatabaseRequired();
    });
}

 // LOGOUT handled by auth.js


 // TOOLTIP //
document.addEventListener("DOMContentLoaded", function () {
    const tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );

    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});

function openSUSModal() { 

    window.open("https://docs.google.com/forms/d/e/1FAIpQLScN5oNctxkFbEYmKcboImZsmEgrApA8KCT_VX4_fRTDN48WMw/viewform?usp=publish-editor", "_blank"); 

} 

//PROFILE
function openProfile() { 
    window.location.href = "../html/profile.html"; 
} 
