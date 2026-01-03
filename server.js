require('dotenv').config();

const express = require('express');
const mysql = require('mysql2');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const cors = require('cors');

const app = express();
app.use(express.json());
app.use(cors());

// 1. Connect to MySQL using .env variables
const db = mysql.createConnection({
    host: process.env.DB_HOST,
    user: process.env.DB_USER, 
    password: process.env.DB_PASS,
    database: process.env.DB_NAME
});


const SECRET_KEY = process.env.JWT_SECRET;

// Middleware to verify JWT Token
const authenticateToken = (req, res, next) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN

    if (!token) return res.sendStatus(401); // No token found

    jwt.verify(token, SECRET_KEY, (err, user) => {
        if (err) return res.sendStatus(403); // Token invalid
        req.user = user; // Attach user info to the request
        next();
    });
};


// 2. REGISTER Route (Sign Up)
app.post('/register', async (req, res) => {
    const { full_name, email, password, role } = req.body;
    
    // Hash the password (Security Requirement 3.1.1)
    const hashedPassword = await bcrypt.hash(password, 10);

    const sql = 'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)';
    db.query(sql, [full_name, email, hashedPassword, role || 'employee'], (err, result) => {
        if (err) return res.status(500).json({ error: err.message });
        res.status(201).json({ message: 'User registered!' });
    });
});

// 3. LOGIN Route (Sign In)
app.post('/login', (req, res) => {
    const { email, password } = req.body;

    const sql = 'SELECT * FROM users WHERE email = ?';
    db.query(sql, [email], async (err, results) => {
        if (err || results.length === 0) return res.status(401).json({ message: 'Invalid credentials' });

        const user = results[0];

        // Check password
        const isMatch = await bcrypt.compare(password, user.password_hash);
        if (!isMatch) return res.status(401).json({ message: 'Invalid credentials' });

        // Generate Token
        const token = jwt.sign({ id: user.id, role: user.role }, SECRET_KEY, { expiresIn: '1d' });
        
        res.json({ token, user: { id: user.id, name: user.full_name, role: user.role } });
    });
});

// --- ATTENDANCE ROUTES ---

// 1. Employee Check-In
app.post('/attendance/check-in', authenticateToken, (req, res) => {
    const userId = req.user.id;
    const date = new Date().toISOString().split('T')[0]; // Current YYYY-MM-DD
    const time = new Date().toTimeString().split(' ')[0]; // Current HH:MM:SS

    // Check if already checked in today
    db.query('SELECT * FROM attendance WHERE user_id = ? AND date = ?', [userId, date], (err, results) => {
        if (results.length > 0) return res.status(400).json({ message: 'Already checked in today' });

        const sql = 'INSERT INTO attendance (user_id, date, check_in_time, status) VALUES (?, ?, ?, ?)';
        db.query(sql, [userId, date, time, 'present'], (err, result) => {
            if (err) return res.status(500).json({ error: err.message });
            res.json({ message: 'Checked in successfully!' });
        });
    });
});

// 2. Employee Check-Out
app.post('/attendance/check-out', authenticateToken, (req, res) => {
    const userId = req.user.id;
    const date = new Date().toISOString().split('T')[0];
    const time = new Date().toTimeString().split(' ')[0];

    const sql = 'UPDATE attendance SET check_out_time = ? WHERE user_id = ? AND date = ?';
    db.query(sql, [time, userId, date], (err, result) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json({ message: 'Checked out successfully!' });
    });
});

// 3. Get My Attendance History
app.get('/attendance/my-history', authenticateToken, (req, res) => {
    db.query('SELECT * FROM attendance WHERE user_id = ?', [req.user.id], (err, results) => {
        res.json(results);
    });
});

// --- LEAVE ROUTES ---

// 1. Apply for Leave
app.post('/leaves/apply', authenticateToken, (req, res) => {
    const { leave_type, start_date, end_date, reason } = req.body;
    const userId = req.user.id;

    const sql = 'INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)';
    db.query(sql, [userId, leave_type, start_date, end_date, reason], (err, result) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json({ message: 'Leave request submitted!' });
    });
});

// 2. View My Leave Requests
app.get('/leaves/my-requests', authenticateToken, (req, res) => {
    db.query('SELECT * FROM leave_requests WHERE user_id = ?', [req.user.id], (err, results) => {
        res.json(results);
    });
});

// 3. Admin: View ALL Requests
app.get('/admin/leaves', authenticateToken, (req, res) => {
    // Security Check: Only allow if role is admin
    if (req.user.role !== 'admin') return res.sendStatus(403);

    // Join with users table to get the name of the person asking for leave
    const sql = `
        SELECT leave_requests.*, users.full_name 
        FROM leave_requests 
        JOIN users ON leave_requests.user_id = users.id
    `;
    db.query(sql, (err, results) => {
        res.json(results);
    });
});

// 4. Admin: Approve/Reject Leave
app.put('/admin/leaves/:id', authenticateToken, (req, res) => {
    if (req.user.role !== 'admin') return res.sendStatus(403);

    const { status } = req.body; // 'approved' or 'rejected'
    const leaveId = req.params.id;

    db.query('UPDATE leave_requests SET status = ? WHERE id = ?', [status, leaveId], (err, result) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json({ message: `Leave request ${status}` });
    });
});

app.listen(3000, () => console.log('Server running on port 3000'));