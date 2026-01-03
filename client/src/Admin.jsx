import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from './api';
import { LogOut, Check, X, Users, ClipboardList } from 'lucide-react';

export default function Admin() {
  const [leaves, setLeaves] = useState([]);
  const navigate = useNavigate();

  useEffect(() => {
    api.get('/admin/leaves').then(res => setLeaves(res.data)).catch(console.error);
  }, []);

  const updateStatus = async (id, status) => {
    await api.put(`/admin/leaves/${id}`, { status });
    setLeaves(leaves.map(l => l.id === id ? { ...l, status } : l));
  };

  return (
    <div className="min-h-screen bg-gray-100 font-sans">
      <nav className="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center shadow-sm">
        <h1 className="text-xl font-bold text-gray-800 flex items-center gap-2">
          <Users className="text-blue-600" /> Admin Portal
        </h1>
        <button onClick={() => { localStorage.clear(); navigate('/'); }} className="flex items-center gap-2 text-sm font-medium text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors">
          <LogOut size={16} /> Logout
        </button>
      </nav>

      <div className="max-w-6xl mx-auto p-8">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-3 bg-blue-100 text-blue-600 rounded-lg">
            <ClipboardList size={24} />
          </div>
          <div>
            <h2 className="text-2xl font-bold text-gray-800">Leave Requests</h2>
            <p className="text-gray-500 text-sm">Manage employee time-off requests</p>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <table className="w-full text-left">
            <thead className="bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider">
              <tr>
                <th className="px-6 py-4">Employee</th>
                <th className="px-6 py-4">Dates</th>
                <th className="px-6 py-4">Reason</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {leaves.map(l => (
                <tr key={l.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-6 py-4 font-semibold text-gray-800">{l.full_name}</td>
                  <td className="px-6 py-4 text-sm text-gray-600">
                    {new Date(l.start_date).toLocaleDateString()} - {new Date(l.end_date).toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">{l.reason}</td>
                  <td className="px-6 py-4">
                    <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase
                      ${l.status === 'approved' ? 'bg-green-100 text-green-700' : 
                        l.status === 'rejected' ? 'bg-red-100 text-red-700' : 
                        'bg-yellow-100 text-yellow-700'}`}>
                      {l.status}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    {l.status === 'pending' && (
                      <div className="flex gap-2">
                        <button onClick={() => updateStatus(l.id, 'approved')} className="p-2 bg-green-50 text-green-600 rounded hover:bg-green-100 transition-colors" title="Approve">
                          <Check size={18} />
                        </button>
                        <button onClick={() => updateStatus(l.id, 'rejected')} className="p-2 bg-red-50 text-red-600 rounded hover:bg-red-100 transition-colors" title="Reject">
                          <X size={18} />
                        </button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
              {leaves.length === 0 && (
                <tr><td colSpan="5" className="px-6 py-8 text-center text-gray-400">No pending requests.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}