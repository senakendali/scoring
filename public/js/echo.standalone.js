
// Dummy echo.standalone.js
window.Pusher = function () {};
window.Echo = function (config) {
    console.log("✅ Dummy Echo initialized with config:", config);
    return {
        channel: function(name) {
            return {
                listen: function(event, callback) {
                    console.log(`Listening to ${event} on channel ${name}`);
                }
            }
        }
    };
};
