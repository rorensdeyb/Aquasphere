/**
 * Admin dead-session guard.
 * PHP sessions live on the ephemeral container, so a redeploy wipes them.
 * If this page loads with no live session, go to login instead of sitting
 * on a stale admin page whose API calls all fail. Unreachable servers
 * (mid-deploy) never trigger a redirect - only an explicit HTTP 401.
 */
(function () {
    fetch('../api/get_current_user.php', { credentials: 'include' })
        .then(function (resp) {
            if (resp.status === 401) {
                window.location.href = '../login.html';
            }
        })
        .catch(function () { /* server unreachable - stay put */ });
})();
