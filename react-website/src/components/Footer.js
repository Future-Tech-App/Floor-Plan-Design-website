import React from 'react';
import { Link } from 'react-router-dom';
import { FaGooglePlay, FaApple, FaInstagram, FaYoutube } from 'react-icons/fa';
import { MdEmail } from 'react-icons/md';

export default function Footer() {
  return (
    <footer className="footer-v2">
      {/* Top glow line */}
      <div className="fv2-topline" />

      <div className="container">
        <div className="fv2-grid">

          {/* ── Brand column ── */}
          <div className="fv2-brand">
            {/* Logo */}
            <div className="fv2-logo">
              <div className="fv2-logo-icon">
                <img src="/images/app logo.jpeg" alt="Floor Plan Designer" className="fv2-logo-img" />
              </div>
              <div className="fv2-logo-text">
                <span className="fv2-logo-name">Floor Plan</span>
                <span className="fv2-logo-sub">Designer</span>
              </div>
            </div>

            <p className="fv2-desc">
              Design professional floor plans, preview in 3D, and export with
              precision — all from your mobile device.
            </p>

            {/* Store badges */}
            <div className="fv2-stores">
              <a
                href="https://play.google.com/store/apps/details?id=com.buildsmart.homedesigns"
                className="fv2-store-btn"
                target="_blank"
                rel="noopener noreferrer"
              >
                <FaGooglePlay className="fv2-store-icon" />
                <span>
                  <small>GET IT ON</small>
                  Google Play
                </span>
              </a>
              <a
                href="https://apps.apple.com/us/app/floor-plan-designer-home-map/id6760694900"
                className="fv2-store-btn"
                target="_blank"
                rel="noopener noreferrer"
              >
                <FaApple className="fv2-store-icon" />
                <span>
                  <small>DOWNLOAD ON THE</small>
                  App Store
                </span>
              </a>
            </div>
          </div>

          {/* ── Product links ── */}
          <div className="fv2-col">
            <h4 className="fv2-heading">Product</h4>
            <div className="fv2-links">
              <Link to="/">Features</Link>
              <Link to="/support">Support</Link>
              <a href="#download">Download</a>
              <Link to="/support#guides">Quick Start</Link>
              <Link to="/support#faq">FAQ</Link>
            </div>
          </div>

          {/* ── Legal links ── */}
          <div className="fv2-col">
            <h4 className="fv2-heading">Legal</h4>
            <div className="fv2-links">
              <Link to="/privacy-policy">Privacy Policy</Link>
              <Link to="/terms-of-use">Terms of Use</Link>
              <Link to="/contact">Contact Us</Link>
            </div>
          </div>

          {/* ── Contact ── */}
          <div className="fv2-col">
            <h4 className="fv2-heading">Get in Touch</h4>
            <div className="fv2-contact-items">
              <a href="mailto:support@floorplandesigner.app" className="fv2-contact-item">
                <MdEmail />
                <span>support@floorplandesigner.app</span>
              </a>
            </div>
            <div className="fv2-social">
              <a href="#instagram" aria-label="Instagram" className="fv2-social-btn"><FaInstagram /></a>
              <a href="#youtube" aria-label="YouTube" className="fv2-social-btn"><FaYoutube /></a>
            </div>
          </div>
        </div>

        {/* ── Bottom bar ── */}
        <div className="fv2-bottom">
          <span className="fv2-copy">
            &copy; {new Date().getFullYear()} <strong>Floor Plan Designer</strong>. All rights reserved.
          </span>
          <span className="fv2-made">
            Crafted with ❤️ for architects &amp; designers
          </span>
        </div>
      </div>
    </footer>
  );
}
