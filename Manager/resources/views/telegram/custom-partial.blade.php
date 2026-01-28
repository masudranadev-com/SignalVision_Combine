<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>User Details</title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <style>
    body {
      font-family: sans-serif;
      padding: 20px;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
    }
    button {
      margin-top: 20px;
      padding: 10px;
      width: 100%;
      background: #2a9df4;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 16px;
    }
  </style>
</head>
<body>
  <h2>📝 Fill Your TP %</h2>

  <input type="text" id="firstName" placeholder="TP1" required>
  <input type="text" id="firstName" placeholder="TP2" required>
  <input type="text" id="firstName" placeholder="TP3" required>
  <input type="text" id="firstName" placeholder="TP4" required>
  <input type="text" id="firstName" placeholder="TP5" required>
  <input type="text" id="firstName" placeholder="TP6" required>

  <button onclick="submitForm()">✅ Submit</button>

  <script>
    function submitForm() {
      const data = {
        first_name: document.getElementById("firstName").value,
        last_name: document.getElementById("lastName").value,
        email: document.getElementById("email").value,
        plan: document.getElementById("dropdown").value
      };

      Telegram.WebApp.sendData(JSON.stringify(data));
      Telegram.WebApp.close();
    }

    Telegram.WebApp.ready();
  </script>
</body>
</html>
