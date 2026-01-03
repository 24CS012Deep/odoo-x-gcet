import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from './api';
import { LogOut, Calendar, Clock, User, CheckCircle } from 'lucide-react';

export default function Dashboard() {
  const [history, setHistory] = useState([]);
  const user = JSON.parse(localStorage.getItem('user')) || { name: 'Employee' };
  const navigate = useNavigate();

  useEffect(() => {
    api.get('/attendance/my-history').then(res => setHistory(res.data)).catch(console.error);
  }, []);

  const handleCheck = async (type) => {
    try {
      await api.post(`/attendance/${type}`);
      alert(type === 'check-in' ? 'Checked In Successfully!' : 'Checked Out Successfully!');
      window.location.reload();
    } catch (err) { alert(err.response?.data?.message); }
  };

  return (
    <div className="min-h-screen bg-gray-50 font-sans">
      {/* Navbar */}
      <nav className="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center sticky top-0 z-10">
        <div className="flex items-center gap-2 text-blue-600 font-bold text-xl">
          <Calendar size={24} /> Dayflow
        </div>
        <div className="flex items-center gap-4">
          <div className="text-right hidden sm:block">
            <p className="text-sm font-semibold text-gray-800">{user.name}</p>
            <p className="text-xs text-gray-500 uppercase">{user.role}</p>
          </div>
          <button onClick={() => { localStorage.clear(); navigate('/'); }} className="p-2 text-red-500 hover:bg-red-50 rounded-full transition-colors">
            <LogOut size={20} />
          </button>
        </div>
      </nav>

      <div className="max-w-5xl mx-auto p-6 space-y-8">
        {/* Welcome & Action Card */}
        <div className="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl p-8 text-white shadow-lg flex flex-col md:flex-row justify-between items-center gap-6">
          <div>
            <h1 className="text-3xl font-bold">Hello, {user.name.split(' ')[0]}!</h1>
            <p className="opacity-90 mt-1">Mark your attendance for today.</p>
          </div>
          <div className="flex gap-4">
            <button onClick={() => handleCheck('check-in')} className="flex items-center gap-2 bg-white text-blue-700 px-6 py-3 rounded-xl font-bold shadow-md hover:bg-gray-100 transition-all active:scale-95">
              <CheckCircle size={20} /> Check In
            </button>
            <button onClick={() => handleCheck('check-out')} className="flex items-center gap-2 bg-indigo-900 text-white px-6 py-3 rounded-xl font-bold shadow-md hover:bg-indigo-950 transition-all active:scale-95">
              <LogOut size={20} /> Check Out
            </button>
          </div>
        </div>

        {/* History Table */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h2 className="text-lg font-semibold text-gray-700 flex items-center gap-2">
              <Clock size={18} /> Attendance History
            </h2>
          </div>
          <table className="w-full text-left">
            <thead className="bg-gray-50 text-gray-500 text-xs uppercase font-medium">
              <tr>
                <th className="px-6 py-3">Date</th>
                <th className="px-6 py-3">In Time</th>
                <th className="px-6 py-3">Out Time</th>
                <th className="px-6 py-3">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {history.map(h => (
                <tr key={h.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-6 py-4 font-medium text-gray-800">{new Date(h.date).toLocaleDateString()}</td>
                  <td className="px-6 py-4 text-gray-600">{h.check_in_time || '--:--'}</td>
                  <td className="px-6 py-4 text-gray-600">{h.check_out_time || '--:--'}</td>
                  <td className="px-6 py-4">
                    <span className="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 uppercase">
                      {h.status}
                    </span>
                  </td>
                </tr>
              ))}
              {history.length === 0 && (
                <tr><td colSpan="4" className="px-6 py-8 text-center text-gray-400">No records found.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}