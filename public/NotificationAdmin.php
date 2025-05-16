<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Push</title>
</head>
<body>
    <h1>Notification Push</h1>

<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.22.2/firebase-app.js";
import { getMessaging, getToken  } from "https://www.gstatic.com/firebasejs/9.22.2/firebase-messaging.js";


const firebaseConfig = {
  apiKey: "AIzaSyB_ERTfli0ommsjJXSyVOJkc3AC4iWhC78", // Servis hesabı JSON'da yer almaz
  authDomain: "ruya-tabiri-79c05.firebaseapp.com",
  projectId: "ruya-tabiri-79c05",
  storageBucket: "ruya-tabiri-79c05.appspot.com",
  messagingSenderId: "65749199369", // client_id değil! Bu örnekte aynı olabilir ama doğruluğu garanti değil
  appId: "1:65749199369:web:56dd0a9ebf92be2ea6a6d8", // JSON'da yer almaz
  measurementId: "" // opsiyonel
};

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);


navigator.serviceWorker.register("../config/sw.js")
  .then((registration) => {
    getToken(messaging, { vapidKey: "BHppvn4WvX_HIX_qnCKeruBJbhBjdysGjqbZ4eDT1NKu_aksQbWtwkgO5Yn_xYNEOXaf6f7xENOD-UJkOCEkdDc" })
      .then((currentToken) => {
        if (currentToken) {
          console.log("Token alındı:", currentToken);
          // Token'i sunucuya gönder
        } else {
          console.log("No registration token available. Request permission to generate one.");
        }
      })
      .catch((err) => {
        console.log("An error occurred while retrieving token. ", err);
      });
})
  .catch((err) => {
    console.error("Service Worker registration failed:", err);
  });



</script>

</body>
</html>
