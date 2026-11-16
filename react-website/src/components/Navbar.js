import React, { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { HiOutlineMenu, HiOutlineX } from 'react-icons/hi';
export default function Navbar() {
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const location = useLocation();

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', onScroll);
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => setOpen(false), [location]);

  const isActive = (path) => location.pathname === path ? 'active' : '';

  return (
    <nav className={`navbar ${scrolled ? 'scrolled' : ''}`}>
      <div className="container">
        <Link to="/" className="navbar-brand">
          <img src="/images/app logo.jpeg" alt="Floor Plan Designer" className="navbar-logo-img" />
          Floor Plan Designer
        </Link>

        <button className="mobile-toggle" onClick={() => setOpen(!open)} aria-label="Toggle menu">
          {open ? <HiOutlineX /> : <HiOutlineMenu />}
        </button>

        <div className={`navbar-links ${open ? 'open' : ''}`}>
          <Link to="/" className={isActive('/')}>Home</Link>
          <Link to="/support" className={isActive('/support')}>Support</Link>
          <Link to="/contact" className={isActive('/contact')}>Contact</Link>
          <Link to="/privacy-policy" className={isActive('/privacy-policy')}>Privacy</Link>
          <a href="#download" className="navbar-cta">Download App</a>
        </div>
      </div>
    </nav>
  );
}
