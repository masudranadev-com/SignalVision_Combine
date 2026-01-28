<!DOCTYPE html>
<html>
<head>
    <title>Laravel WebSockets Test</title>
    <meta name="csrf" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/app.js'])
</head>
<body>
    <h1>WebSockets Test</h1>
    <button id="sendMessage">Send Message</button>
    <div id="messages"></div>
</body>
</html>