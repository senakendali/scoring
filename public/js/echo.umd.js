/*! Laravel Echo - UMD Build */
class Echo { constructor(options) { console.log('Echo initialized', options); } channel(name) { return { listen(event, callback) { console.log('Listening to', name, event); } }; } }
window.Echo = Echo;