// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyCmnqOzz-8PJoTumemzUF8_5T5l-ztSmQw",
  authDomain: "parkpicnic-ea849.firebaseapp.com",
  projectId: "parkpicnic-ea849",
  storageBucket: "parkpicnic-ea849.firebasestorage.app",
  messagingSenderId: "349910337633",
  appId: "1:349910337633:web:f5a0d99641597cfb8f8220",
  measurementId: "G-0QC60KL808"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);