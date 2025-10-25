const express = require('express');
const sqlite3 = require('sqlite3').verbose();
const path = require('path');
const nodemailer = require('nodemailer');
const twilio = require('twilio');

const app = express();
const PUBLIC_DIR = path.join(__dirname, 'public');

const PORT = Number(process.env.PORT) || 3000;

app.use(express.json());
app.use(express.static(PUBLIC_DIR));

// База SQLite
const dbFile = path.join(__dirname, 'db.sqlite');
const db = new sqlite3.Database(dbFile);

db.serialize(() => {
  db.run(`
    CREATE TABLE IF NOT EXISTS gazebos (
      id INTEGER PRIMARY KEY,
      name TEXT NOT NULL UNIQUE
    );
  `);
  db.run(`
    CREATE TABLE IF NOT EXISTS bookings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      gazebo_id INTEGER NOT NULL,
      date TEXT NOT NULL,  -- YYYY-MM-DD
      name TEXT NOT NULL,
      phone TEXT NOT NULL,
      email TEXT NOT NULL,
      comment TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      UNIQUE (gazebo_id, date),
      FOREIGN KEY (gazebo_id) REFERENCES gazebos(id)
    );
  `);

  db.get(`SELECT COUNT(*) AS cnt FROM gazebos`, (err, row) => {
    if (err) return console.error(err);
    if (row.cnt === 0) {
      const stmt = db.prepare(`INSERT INTO gazebos (id, name) VALUES (?, ?)`);
      for (let i = 1; i <= 6; i++) stmt.run(i, `Беседка №${i}`);
      stmt.finalize();
      console.log('Gazebos seeded (1..6)');
    }
  });
});


const isDate = (s) => /^\d{4}-\d{2}-\d{2}$/.test(s);
const toInt = (v) => Number.isFinite(parseInt(v, 10)) ? parseInt(v, 10) : null;


const MAIL_HOST = process.env.MAIL_HOST;
const MAIL_PORT = parseInt(process.env.MAIL_PORT || '587', 10);
const MAIL_USER = process.env.MAIL_USER;
const MAIL_PASS = process.env.MAIL_PASS;
let mailer = null;
if (MAIL_HOST && MAIL_USER && MAIL_PASS) {
  mailer = nodemailer.createTransport({
    host: MAIL_HOST,
    port: MAIL_PORT,
    secure: MAIL_PORT === 465,
    auth: { user: MAIL_USER, pass: MAIL_PASS }
  });
}


const TWILIO_SID = process.env.TWILIO_SID;
const TWILIO_TOKEN = process.env.TWILIO_TOKEN;
const TWILIO_FROM = process.env.TWILIO_FROM; 
let smsClient = null;
if (TWILIO_SID && TWILIO_TOKEN && TWILIO_FROM) {
  smsClient = twilio(TWILIO_SID, TWILIO_TOKEN);
}


app.get('/api/gazebos', (req, res) => {
  db.all(`SELECT id, name FROM gazebos ORDER BY id ASC`, (err, rows) => {
    if (err) return res.status(500).json({ error: 'DB error' });
    res.json(rows);
  });
});


app.get('/api/booked', (req, res) => {
  const gazebo = toInt(req.query.gazebo);
  const year = toInt(req.query.year);
  const month1 = toInt(req.query.month); 

  if (!gazebo || !year || !month1) {
    return res.status(400).json({ error: 'gazebo, year, month are required' });
  }

  const mm = String(month1).padStart(2, '0');
  const start = `${year}-${mm}-01`;
  const end = `${year}-${mm}-31`;

  db.all(
    `SELECT date FROM bookings WHERE gazebo_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC`,
    [gazebo, start, end],
    (err, rows) => {
      if (err) return res.status(500).json({ error: 'DB error' });
      res.json({ dates: rows.map(r => r.date) });
    }
  );
});


app.post('/api/bookings', (req, res) => {
  const { gazebo_id, date, name, phone, email, comment } = req.body || {};
  const gid = toInt(gazebo_id);

  if (!gid) return res.status(400).json({ error: 'gazebo_id is required' });
  if (!isDate(date)) return res.status(400).json({ error: 'Invalid date format (YYYY-MM-DD)' });
  if (!name || !phone || !email) return res.status(400).json({ error: 'name, phone, email are required' });

  const now = new Date(); now.setHours(0,0,0,0);
  const chosen = new Date(date);
  if (chosen < now) return res.status(400).json({ error: 'Past dates are not allowed' });

  db.run(
    `INSERT INTO bookings (gazebo_id, date, name, phone, email, comment) VALUES (?, ?, ?, ?, ?, ?)`,
    [gid, date, String(name).trim(), String(phone).trim(), String(email).trim(), String(comment || '').trim()],
    function (err) {
      if (err) {
        if (String(err.message || '').includes('UNIQUE')) {
          return res.status(409).json({ error: 'Date already booked' });
        }
        return res.status(500).json({ error: 'DB error' });
      }

      
      const bookingId = this.lastID;
      res.status(201).json({ id: bookingId });

      const subject = `Подтверждение бронирования беседки #${gid} на ${date}`;
      const bodyText =
        `Здравствуйте, ${name}!\n\n` +
        `Ваша заявка на бронирование беседки №${gid} на дату ${date} принята.\n` +
        `Телефон: ${phone}\n` +
        (comment ? `Комментарий: ${comment}\n` : '') +
        `\nСпасибо, что выбрали нас!`;

      
      if (mailer) {
        mailer.sendMail({
          from: MAIL_USER,
          to: email,
          subject,
          text: bodyText
        }).catch(console.warn);
      }

      
      if (smsClient) {
        smsClient.messages.create({
          body: `Заявка принята: беседка №${gid}, дата ${date}. Спасибо!`,
          from: TWILIO_FROM,
          to: phone
        }).catch(console.warn);
      }
    }
  );
});


app.get(/^(?!\/api\/).*/, (req, res) => {
  res.sendFile(path.join(PUBLIC_DIR, 'index.html'));
});

app.listen(PORT, () => {
  console.log(`Server running at http://localhost:${PORT}`);
});