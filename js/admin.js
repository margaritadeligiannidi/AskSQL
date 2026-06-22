let selectedUserId = null;
let selectedRole = null;
let modal = null;
let allUsers = [];


/* ADMIN PROTECTION */
const payload = parseJwt(getAuthToken());

if (!payload || payload.role !== "admin") {
    window.location.href = "dashboard.html";
}


document.addEventListener("DOMContentLoaded", () => {
      modal = new bootstrap.Modal(
        document.getElementById("confirmModal")
    );

    loadUsers();
     loadUsername();

    document.getElementById("searchUser")
        .addEventListener("input", function () {

            const value = this.value.toLowerCase();

            const filtered = allUsers.filter(user =>
                user.username.toLowerCase().includes(value)
            );

            renderUsers(filtered);
        });

    document.getElementById("confirmBtn").addEventListener("click", () => {

        // API FETCH using token-aware helper
     apiFetch("../api/update_role.php", {

    method: "POST",

    headers: {
        "Content-Type": "application/json"
    },

    body: {
        user_id: selectedUserId,
        role: selectedRole
    }

}).then(data => {

    if (!data || !data.success) {

        alert(data ? data.error : 'Server error');
        return;
    }

    modal.hide();
    loadUsers();
});
});

    document.getElementById('confirmModal').addEventListener('hidden.bs.modal', () => {
        loadUsers();
    });

});


/*  LOAD USERS */
function loadUsers() {

    apiFetch("../api/get_users.php")
    .then(data => {

        if (!data || !data.success) {
            alert(data ? data.error : "Server error");
            return;
        }

        allUsers = data.users;

        document.getElementById("totalUsers").textContent =
            allUsers.length;

        renderUsers(allUsers);
    });
}


/*  RENDER TABLE*/
function renderUsers(users) {

    const table = document.getElementById("usersTable");
    table.innerHTML = "";

    users.forEach(user => {

        let badgeClass =
            user.role === "admin" ? "danger" :
            user.role === "full"  ? "success" :
                                   "secondary";

        const row = `
            <tr>
                <td>${user.id}</td>
                <td><strong>${escapeHtml(user.username)}</strong></td>

                <td>
                    <span class="badge bg-${badgeClass}">
                        ${user.role.toUpperCase()}
                    </span>
                </td>

                <td>
                    <select class="form-select form-select-sm"
                        onchange="updateRole(${user.id}, this.value)">

                        <option value="demo" ${user.role==="demo"?"selected":""}>Demo</option>
                        <option value="full" ${user.role==="full"?"selected":""}>Full</option>
                        <option value="admin" ${user.role==="admin"?"selected":""}>Admin</option>
                    </select>
                </td>
            </tr>
        `;

        table.innerHTML += row;
    });
}


/*  OPEN MODAL */
function updateRole(userId, role) {

    selectedUserId = userId;
    selectedRole = role;

    // μήνυμα
    document.getElementById("confirmText").innerText =
        "Are you sure you want to change this user's role?";

    modal.show();
}


/* PROTECTION */
function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
}

async function loadUsername() {

    const data = await apiFetch("../api/profile.php");

    if (data && data.username) {
        document.getElementById("navbarUsername").innerText =
            data.username;
    }
}


