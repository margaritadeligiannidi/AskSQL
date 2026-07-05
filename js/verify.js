const icon = document.getElementById("icon");
const message = document.getElementById("message");
const loginButton = document.getElementById("loginButton");

/* TOKEN */

const token = new URLSearchParams(window.location.search).get("token");

/* VERIFY */

if (!token) {

    icon.innerHTML =
        '<i class="bi bi-x-circle-fill text-danger"></i>';

    message.className =
        "alert alert-danger mt-3";

    message.textContent =
        "Invalid verification link.";

    loginButton.classList.remove("d-none");

} else {

    fetch("../api/verify.php", {

        method: "POST",

        headers: {
            "Content-Type": "application/json"
        },

        body: JSON.stringify({
            token: token
        })

    })

    .then(response => response.json())

    .then(data => {

        message.textContent = data.message;

        message.className =
            "alert alert-" + data.type + " mt-3";

        switch (data.type) {

            case "success":

                icon.innerHTML =
                    '<i class="bi bi-check-circle-fill text-success"></i>';

                loginButton.classList.remove("d-none");

                setTimeout(() => {

                    window.location.href = "login.html";

                }, 2500);

                break;

            case "info":

                icon.innerHTML =
                    '<i class="bi bi-info-circle-fill text-primary"></i>';

                loginButton.classList.remove("d-none");

                break;

            default:

                icon.innerHTML =
                    '<i class="bi bi-x-circle-fill text-danger"></i>';

                loginButton.classList.remove("d-none");

        }

    })

    .catch(error => {

        console.error(error);

        icon.innerHTML =
            '<i class="bi bi-x-circle-fill text-danger"></i>';

        message.className =
            "alert alert-danger mt-3";

        message.textContent =
            "Unable to verify your account.";

        loginButton.classList.remove("d-none");

    });

}