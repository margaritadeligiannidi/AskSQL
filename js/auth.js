const API_TOKEN_KEY = 'asksql_token';
const API_CONNECTION_KEY = 'asksql_connection_id';


function parseJwt(token) {

    if (!token) {
        return null;
    }

    try {

        const base64Url = token.split('.')[1];

        const base64 = base64Url
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const jsonPayload = decodeURIComponent(
            atob(base64)
                .split('')
                .map(c =>
                    '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
                )
                .join('')
        );

        return JSON.parse(jsonPayload);

    } catch (e) {

        console.error('JWT parse error', e);
        return null;
    }
}
function getAuthToken() {
    return localStorage.getItem(API_TOKEN_KEY);
}

function setAuthToken(token) {
    localStorage.setItem(API_TOKEN_KEY, token);
}

function clearAuthToken() {
    localStorage.removeItem(API_TOKEN_KEY);
}

function getConnectionId() {
    return localStorage.getItem(API_CONNECTION_KEY);
}

function setConnectionId(connectionId) {
    if (connectionId) {
        localStorage.setItem(API_CONNECTION_KEY, connectionId);
    }
}

function clearConnectionId() {
    localStorage.removeItem(API_CONNECTION_KEY);
}

function redirectToLoginIfUnauthenticated() {
    if (!getAuthToken()) {
        window.location.href = 'login.html';
    }
}

function redirectIfAuthenticated() {
    if (getAuthToken()) {
        window.location.href = 'html/connections.html'.replace('html/','');
    }
}

function requireConnectionId() {
    if (!getConnectionId()) {
        window.location.href = 'html/connections.html'.replace('html/','');
    }
}

function apiFetch(url, options = {}) {
    const token = getAuthToken();
    const fetchOptions = {
        method: options.method || 'GET',
        ...options,
        headers: {
            ...(options.headers || {}),
        }
    };

    if (token) {
        fetchOptions.headers['Authorization'] = `Bearer ${token}`;
    }

    // Attach connection_id automatically when available
    const connectionId = getConnectionId();
    try {
        // For GET requests - append as query param if not present
        if (fetchOptions.method.toUpperCase() === 'GET' && connectionId) {
            const hasQuery = url.includes('?');
            const param = `connection_id=${encodeURIComponent(connectionId)}`;
            if (!url.includes('connection_id=')) {
                url = url + (hasQuery ? '&' : '?') + param;
            }
        }

        // For FormData - append field
        if (fetchOptions.body instanceof FormData && connectionId) {
            if (!fetchOptions.body.get('connection_id')) {
                fetchOptions.body.append('connection_id', connectionId);
            }
        }

        // For JSON bodies - inject connection_id into object before stringifying
        const isFormData = fetchOptions.body instanceof FormData;
        if (!isFormData && fetchOptions.body && typeof fetchOptions.body === 'object') {
            if (connectionId && !fetchOptions.body.connection_id) {
                fetchOptions.body.connection_id = connectionId;
            }
        }
    } catch (e) {
        // ignore and continue
        console.error('attach-connection-id-error', e);
    }

    const isFormData = fetchOptions.body instanceof FormData;
    if (!isFormData && fetchOptions.body && typeof fetchOptions.body === 'object') {
        if (!fetchOptions.headers['Content-Type']) {
            fetchOptions.headers['Content-Type'] = 'application/json';
        }
        fetchOptions.body = JSON.stringify(fetchOptions.body);
    }

    return fetch(url, fetchOptions)
        .then(async res => {
            if (res.status === 401) {
                clearAuthToken();
                clearConnectionId();
                window.location.href = 'login.html';
                return null;
            }

            const text = await res.text();
            if (!text) {
                return null;
            }

            try {
                return JSON.parse(text);
            } catch (err) {
                console.error('API RESPONSE PARSE ERROR:', err, text);
                return null;
            }
        })
        .catch(err => {
            console.error('API ERROR:', err);
            return null;
        });
}

function logout() {
    clearAuthToken();
    clearConnectionId();
    window.location.href = 'login.html';
}