import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { User, Lock, Mail, Eye, EyeOff } from 'lucide-react';
import api from './api'; // Connection to your backend

const LoginPage = () => {
  const [isLogin, setIsLogin] = useState(true);
  const [showPassword, setShowPassword] = useState(false);
  const navigate = useNavigate();
  
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    confirmPassword: '',
    fullName: '',
    employeeId: '',
    role: 'employee',
    rememberMe: false
  });

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    try {
      if (isLogin) {
        // --- LOGIN LOGIC ---
        const res = await api.post('/login', { 
            email: formData.email, 
            password: formData.password 
        });

        // Save Token & User Data
        localStorage.setItem('token', res.data.token);
        localStorage.setItem('user', JSON.stringify(res.data.user));

        // Redirect based on Role
        if (res.data.user.role === 'admin') {
            navigate('/admin');
        } else {
            navigate('/dashboard');
        }

      } else {
        // --- REGISTER LOGIC ---
        
        // 1. Validate Passwords match
        if (formData.password !== formData.confirmPassword) {
            alert("Passwords do not match!");
            return;
        }

        // 2. Prepare data for Backend (Mapping names)
        const payload = {
            full_name: formData.fullName, // Backend expects 'full_name'
            email: formData.email,
            password: formData.password,
            role: formData.role 
            // Note: employeeId is in UI but not sent to DB yet as DB doesn't have that column
        };

        await api.post('/register', payload);
        alert('Registration Successful! Please Login.');
        setIsLogin(true); // Switch to login view
      }
    } catch (err) {
      console.error(err);
      alert(err.response?.data?.message || 'Something went wrong. Please try again.');
    }
  };

  return (
    <div className="min-h-screen flex">
      {/* Left Side - Illustration */}
      <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-orange-100 to-gray-100 items-center justify-center p-12">
        <div className="max-w-md text-center">
          <div className="mb-8">
            <svg className="w-64 h-64 mx-auto" viewBox="0 0 400 400" fill="none">
              {/* Clock */}
              <circle cx="180" cy="120" r="40" stroke="#d1d5db" strokeWidth="2" fill="white" strokeDasharray="5,5"/>
              <line x1="180" y1="120" x2="180" y2="100" stroke="#6b7280" strokeWidth="2"/>
              <line x1="180" y1="120" x2="195" y2="130" stroke="#6b7280" strokeWidth="2"/>
              
              {/* Person */}
              <circle cx="200" cy="200" r="25" fill="#ff6b35"/>
              <ellipse cx="200" cy="250" rx="35" ry="50" fill="#ff6b35"/>
              <line x1="165" y1="220" x2="140" y2="260" stroke="#1f2937" strokeWidth="8" strokeLinecap="round"/>
              <line x1="235" y1="220" x2="260" y2="260" stroke="#1f2937" strokeWidth="8" strokeLinecap="round"/>
              <line x1="185" y1="300" x2="180" y2="340" stroke="#1f2937" strokeWidth="8" strokeLinecap="round"/>
              <line x1="215" y1="300" x2="220" y2="340" stroke="#1f2937" strokeWidth="8" strokeLinecap="round"/>
              
              {/* Pencil */}
              <rect x="250" y="240" width="10" height="60" fill="#fbbf24" transform="rotate(20 255 270)"/>
              <polygon points="250,235 255,225 260,235" fill="#1f2937" transform="rotate(20 255 230)"/>
              
              {/* Dashboard screens */}
              <rect x="120" y="260" width="80" height="60" rx="4" fill="white" stroke="#d1d5db" strokeWidth="2"/>
              <rect x="130" y="270" width="20" height="20" fill="#e5e7eb"/>
              <line x1="155" y1="275" x2="185" y2="275" stroke="#d1d5db" strokeWidth="2"/>
              <line x1="155" y1="282" x2="180" y2="282" stroke="#d1d5db" strokeWidth="2"/>
            </svg>
          </div>
          <h2 className="text-3xl font-bold text-gray-800 mb-4">
            Onboarding New Talent with Digital HRMS
          </h2>
          <p className="text-gray-600">
            Everything you need in an easily customizable dashboard
          </p>
          <div className="flex justify-center gap-2 mt-6">
            <div className="w-8 h-2 bg-orange-500 rounded-full"></div>
            <div className="w-2 h-2 bg-gray-300 rounded-full"></div>
            <div className="w-2 h-2 bg-gray-300 rounded-full"></div>
          </div>
        </div>
      </div>

      {/* Right Side - Form */}
      <div className="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white">
        <div className="max-w-md w-full">
          {/* Logo */}
          <div className="text-center mb-8">
            <div className="inline-flex items-center gap-2 mb-4">
              <div className="w-10 h-10 bg-gradient-to-br from-orange-500 to-gray-800 transform rotate-12"></div>
              <div className="w-10 h-10 bg-gradient-to-br from-gray-800 to-orange-500 transform -rotate-12 -ml-6"></div>
            </div>
            <h1 className="text-2xl font-bold text-gray-800 mb-2">
              {isLogin ? 'Welcome Back !' : 'Create Account'}
            </h1>
            <p className="text-gray-500">
              {isLogin ? 'Please enter your details' : 'Sign up to get started'}
            </p>
          </div>

          {/* Form */}
          <div className="space-y-4">
            {!isLogin && (
              <>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Employee ID
                  </label>
                  <div className="relative">
                    <User className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                    <input
                      type="text"
                      name="employeeId"
                      value={formData.employeeId}
                      onChange={handleChange}
                      className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none"
                      placeholder="Enter employee ID"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Full Name
                  </label>
                  <div className="relative">
                    <User className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                    <input
                      type="text"
                      name="fullName"
                      value={formData.fullName}
                      onChange={handleChange}
                      className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none"
                      placeholder="Enter your full name"
                    />
                  </div>
                </div>
              </>
            )}

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Email Address
              </label>
              <div className="relative">
                <Mail className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none"
                  placeholder="Enter your email"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Password
              </label>
              <div className="relative">
                <Lock className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                <input
                  type={showPassword ? "text" : "password"}
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  className="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none"
                  placeholder="Enter your password"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-3 text-gray-400 hover:text-gray-600"
                >
                  {showPassword ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                </button>
              </div>
            </div>

            {!isLogin && (
              <>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Confirm Password
                  </label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                    <input
                      type={showPassword ? "text" : "password"}
                      name="confirmPassword"
                      value={formData.confirmPassword}
                      onChange={handleChange}
                      className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none"
                      placeholder="Confirm your password"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Role
                  </label>
                  <select
                    name="role"
                    value={formData.role}
                    onChange={handleChange}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none"
                  >
                    <option value="employee">Employee</option>
                    {/* UPDATED: Changed value 'hr' to 'admin' to match your database */}
                    <option value="admin">HR / Admin</option>
                  </select>
                </div>
              </>
            )}

            {isLogin && (
              <div className="flex items-center justify-between">
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    name="rememberMe"
                    checked={formData.rememberMe}
                    onChange={handleChange}
                    className="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500"
                  />
                  <span className="ml-2 text-sm text-gray-600">Remember me</span>
                </label>
                <button
                  type="button"
                  className="text-sm text-orange-500 hover:text-orange-600"
                  onClick={() => alert('Password reset functionality')}
                >
                  Forgot Password?
                </button>
              </div>
            )}

            <button
              onClick={handleSubmit}
              className="w-full bg-gray-900 text-white py-3 rounded-lg font-medium hover:bg-gray-800 transition-colors flex items-center justify-center gap-2"
            >
              {isLogin ? 'Login' : 'Sign Up'}
              <span>→</span>
            </button>

            {!isLogin && (
              <p className="text-xs text-center text-gray-500">
                By creating an account, you agree to our{' '}
                <button className="text-blue-600 hover:underline">Terms of Service</button>
                {' '}and{' '}
                <button className="text-blue-600 hover:underline">Privacy Policy</button>
              </p>
            )}

            <p className="text-center text-gray-600">
              {isLogin ? "Don't have an account? " : "Already have an account? "}
              <button
                type="button"
                onClick={() => setIsLogin(!isLogin)}
                className="text-blue-600 hover:underline font-medium"
              >
                {isLogin ? 'Sign Up' : 'Login'}
              </button>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;