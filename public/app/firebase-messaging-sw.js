// Service worker push notification Portal Salaf versi web.
// Disajikan Laravel di /firebase-messaging-sw.js (lihat routes/web.php di portal_salaf).
// Notifikasi saat tab tertutup / di latar belakang ditampilkan otomatis oleh Firebase;
// saat diklik membuka tautan dari server (webpush.fcm_options.link di FcmService).
//
// Nilai di bawah adalah konfigurasi publik aplikasi Web di Firebase Console (bukan rahasia).
// Versi SDK disamakan dengan yang dipakai FlutterFire (firebase_core_web).
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: 'AIzaSyDDLQ2XosCi9n-03TzashFAn2Jfa1hMuqQ',
  authDomain: 'portal-salaf-app.firebaseapp.com',
  projectId: 'portal-salaf-app',
  messagingSenderId: '744373657521',
  appId: '1:744373657521:web:bca752e2b5969113dca790',
});

firebase.messaging();
