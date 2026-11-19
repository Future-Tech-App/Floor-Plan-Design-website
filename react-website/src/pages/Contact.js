import React, { useState } from 'react';
import {
  MdEmail, MdAccessTime, MdLocationOn, MdSend, MdCheckCircle
} from 'react-icons/md';

export default function Contact() {
  const [submitted, setSubmitted] = useState(false);
  const [form, setForm] = useState({
    name: '', email: '', subject: 'general', message: '',
  });

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    // In production, send to a backend/email service
    setSubmitted(true);
  };

  return (
    <>
      <section className="page-hero">
        <div className="container">
          <h1>Contact Us</h1>
          <p>Have a question, suggestion, or found a bug? We'd love to hear from you.</p>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="contact-grid">
            {/* Left - Contact Info */}
            <div>
              <h2 style={{ fontSize: '1.5rem', fontWeight: 700, marginBottom: 8 }}>
                Get in Touch
              </h2>
              <p style={{ color: '#64748B', marginBottom: 28, lineHeight: 1.7 }}>
                We typically respond within 24-48 hours. For urgent issues,
                please include "URGENT" in the subject line.
              </p>

              <ContactInfoCard
                icon={<MdEmail />}
                color="#2196F3"
                title="Email Us"
                desc="support@floorplandesigner.app"
              />
              <ContactInfoCard
                icon={<MdAccessTime />}
                color="#10B981"
                title="Response Time"
                desc="Within 24-48 business hours"
              />
              <ContactInfoCard
                icon={<MdLocationOn />}
                color="#8B5CF6"
                title="Availability"
                desc="Monday - Friday, 9 AM - 6 PM"
              />

              <div style={{
                marginTop: 28,
                padding: 24,
                background: '#F8FAFC',
                borderRadius: 12,
                border: '1px solid #E2E8F0',
              }}>
                <h4 style={{ fontSize: '0.95rem', marginBottom: 8 }}>Before contacting us:</h4>
                <ul style={{ margin: '0 0 0 18px', listStyle: 'disc' }}>
                  <li style={{ fontSize: '0.85rem', color: '#64748B', marginBottom: 4, lineHeight: 1.6 }}>
                    Check our <a href="/support" style={{ color: '#2196F3' }}>Support Center</a> and FAQ for quick answers.
                  </li>
                  <li style={{ fontSize: '0.85rem', color: '#64748B', marginBottom: 4, lineHeight: 1.6 }}>
                    Include your device model and OS version for bug reports.
                  </li>
                  <li style={{ fontSize: '0.85rem', color: '#64748B', lineHeight: 1.6 }}>
                    Screenshots help us understand issues faster.
                  </li>
                </ul>
              </div>
            </div>

            {/* Right - Contact Form */}
            <div className="contact-form">
              {submitted ? (
                <div className="form-success">
                  <div className="form-success-icon"><MdCheckCircle /></div>
                  <h3 style={{ fontSize: '1.3rem', marginBottom: 8 }}>Message Sent!</h3>
                  <p style={{ color: '#64748B', marginBottom: 20 }}>
                    Thank you for reaching out. We'll get back to you within 24-48 hours.
                  </p>
                  <button
                    className="btn btn-outline"
                    onClick={() => {
                      setSubmitted(false);
                      setForm({ name: '', email: '', subject: 'general', message: '' });
                    }}
                  >
                    Send Another Message
                  </button>
                </div>
              ) : (
                <form onSubmit={handleSubmit}>
                  <h3 style={{ fontSize: '1.2rem', fontWeight: 700, marginBottom: 20 }}>
                    Send us a Message
                  </h3>

                  <div className="form-row">
                    <div className="form-group">
                      <label htmlFor="name">Full Name</label>
                      <input
                        type="text"
                        id="name"
                        name="name"
                        value={form.name}
                        onChange={handleChange}
                        placeholder="Your name"
                        required
                      />
                    </div>
                    <div className="form-group">
                      <label htmlFor="email">Email Address</label>
                      <input
                        type="email"
                        id="email"
                        name="email"
                        value={form.email}
                        onChange={handleChange}
                        placeholder="you@example.com"
                        required
                      />
                    </div>
                  </div>

                  <div className="form-group">
                    <label htmlFor="subject">Subject</label>
                    <select
                      id="subject"
                      name="subject"
                      value={form.subject}
                      onChange={handleChange}
                    >
                      <option value="general">General Inquiry</option>
                      <option value="bug">Bug Report</option>
                      <option value="feature">Feature Request</option>
                      <option value="feedback">Feedback</option>
                      <option value="account">Account / Data Issue</option>
                      <option value="other">Other</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label htmlFor="message">Message</label>
                    <textarea
                      id="message"
                      name="message"
                      value={form.message}
                      onChange={handleChange}
                      placeholder="Describe your question or issue in detail..."
                      rows={5}
                      required
                    />
                  </div>

                  <button type="submit" className="form-submit">
                    <MdSend style={{ verticalAlign: 'middle', marginRight: 8 }} />
                    Send Message
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      </section>
    </>
  );
}

function ContactInfoCard({ icon, color, title, desc }) {
  return (
    <div className="contact-info-card">
      <div className="contact-info-icon" style={{ background: `${color}15`, color }}>
        {icon}
      </div>
      <div>
        <h4>{title}</h4>
        <p>{desc}</p>
      </div>
    </div>
  );
}
