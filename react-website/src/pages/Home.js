import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  MdDraw, MdStraighten, MdCalculate, MdChair,
  MdLayers, MdPictureAsPdf, MdViewInAr, MdBubbleChart,
  MdArchitecture, MdTouchApp, Md3dRotation, MdTexture,
  MdDoorFront, MdStairs, MdGridOn, MdHistory,
  MdAutoAwesome, MdHome, MdApartment, MdBusiness,
  MdMeetingRoom, MdKitchen, MdBathtub, MdWeekend, MdCheckCircle,
  MdStar, MdVerified, MdKeyboardArrowDown,
} from 'react-icons/md';
import { FaGooglePlay, FaApple } from 'react-icons/fa';

export default function Home() {
  return (
    <>
      <HeroSection />

      {/* Stats */}
      <section className="section">
        <div className="container">
          <div className="stats-row">
            <div className="stat-item">
              <div className="number">9+</div>
              <div className="label">Powerful Tools</div>
            </div>
            <div className="stat-item">
              <div className="number">40+</div>
              <div className="label">Languages</div>
            </div>
            <div className="stat-item">
              <div className="number">300</div>
              <div className="label">DPI Export</div>
            </div>
            <div className="stat-item">
              <div className="number">3D</div>
              <div className="label">Live Preview</div>
            </div>
            <div className="stat-item">
              <div className="number">5+</div>
              <div className="label">Plan Types</div>
            </div>
          </div>
        </div>
      </section>

      {/* App Preview */}
      <section className="section section-alt">
        <div className="container">
          <div className="app-preview">
            <div className="app-preview-content">
              <h2>Powerful Tools, <span>Simple Interface</span></h2>
              <p>
                Everything you need is right on the home screen — draw floor plans,
                calculate materials, measure with precision, check surface levels,
                and export professional designs. All tools are one tap away.
              </p>
              <ul className="app-preview-list">
                <li><MdCheckCircle className="app-preview-check" /> 9+ professional tools in one app</li>
                <li><MdCheckCircle className="app-preview-check" /> Intuitive card-based home screen</li>
                <li><MdCheckCircle className="app-preview-check" /> Works completely offline</li>
                <li><MdCheckCircle className="app-preview-check" /> 40+ languages supported</li>
              </ul>
              <a href="https://play.google.com/store/apps/details?id=com.buildsmart.homedesigns" className="btn btn-primary" target="_blank" rel="noopener noreferrer">
                <FaGooglePlay /> Download Free
              </a>
            </div>
            <div className="app-preview-phone">
              <img
                src="/images/App-Home-Screen.png"
                alt="Floor Plan Designer - App Home Screen"
                className="app-preview-img"
              />
            </div>
          </div>
        </div>
      </section>

      {/* What You Can Design */}
      <DesignSection />

      {/* How It Works */}
      <section className="section">
        <div className="container">
          <div className="section-header">
            <h2>How It Works</h2>
            <p>Create professional floor plans in four simple steps.</p>
          </div>

          <div className="steps-grid">
            <StepCard
              number="1"
              title="Draw Your Layout"
              desc="Open the editor and start drawing walls using straight wall, freehand, or room tools. Walls snap to grid for precision. Add doors, windows, and stairs to complete the structure."
            />
            <StepCard
              number="2"
              title="Add Furniture & Details"
              desc="Browse the furniture catalog and place items in your rooms. Drag to position, rotate to orient, and resize to fit. Add measurements and labels for clarity."
            />
            <StepCard
              number="3"
              title="Preview in 3D"
              desc="Switch to 3D mode to see your floor plan come to life. Rotate the view with gestures, zoom in on details, and see textured floors, walls, and furniture from every angle."
            />
            <StepCard
              number="4"
              title="Export & Share"
              desc="Export your completed design as a high-quality PDF or image at 300 DPI. Share with contractors, interior designers, or family to bring your vision to reality."
            />
          </div>
        </div>
      </section>

      {/* 3D Preview Deep Dive */}
      <section className="section section-alt">
        <div className="container">
          <div className="section-header">
            <h2>Stunning 3D Preview</h2>
            <p>
              See your floor plan in three dimensions. Every wall, door, window, and piece of furniture
              renders in real-time 3D — right on your device.
            </p>
          </div>

          <div className="preview-showcase">
            {/* 3D Mockup Visual */}
            <div className="preview-visual">
              <div className="preview-3d-mockup">
                <div className="preview-3d-floor">
                  <div className="preview-3d-wall wall-back" />
                  <div className="preview-3d-wall wall-left" />
                  <div className="preview-3d-wall wall-right" />
                  <div className="preview-3d-label">Isometric 3D View</div>
                </div>
              </div>
            </div>

            {/* 3D Feature List */}
            <div className="preview-features">
              <Preview3DFeature
                icon={<Md3dRotation />}
                color="#EC4899"
                title="Interactive Rotation"
                desc="Drag to rotate your floor plan in any direction. Smooth inertia-based physics let you spin and explore naturally with momentum."
              />
              <Preview3DFeature
                icon={<MdTouchApp />}
                color="#8B5CF6"
                title="Pinch to Zoom"
                desc="Zoom from 0.4x to 4x magnification. Inspect fine details up close or zoom out to see the entire building at a glance."
              />
              <Preview3DFeature
                icon={<MdTexture />}
                color="#F59E0B"
                title="Realistic Textures"
                desc="Floors render with world-anchored textures — terrazzo, concrete, wood, marble, and grass. Textures stay stable as you rotate."
              />
              <Preview3DFeature
                icon={<MdAutoAwesome />}
                color="#10B981"
                title="Directional Lighting"
                desc="Walls and surfaces shade dynamically based on face orientation. Side walls, top edges, and floors each receive natural light variation."
              />
              <Preview3DFeature
                icon={<MdDoorFront />}
                color="#2196F3"
                title="Doors & Windows in 3D"
                desc="Doors and windows automatically cut openings in walls. See swing directions, frame placements, and window heights rendered accurately."
              />
              <Preview3DFeature
                icon={<MdChair />}
                color="#EF4444"
                title="3D Furniture"
                desc="Every placed furniture item renders as a 3D object — beds, sofas, tables, toilets, sinks, stairs, and more with correct proportions and colors."
              />
            </div>
          </div>

          {/* 3D Technical Highlights */}
          <div className="preview-tech-row">
            <TechBadge label="Isometric Projection" />
            <TechBadge label="Depth-Sorted Rendering" />
            <TechBadge label="Backface Culling" />
            <TechBadge label="Real-Time on Device" />
            <TechBadge label="No Internet Required" />
            <TechBadge label="Smooth 60fps" />
          </div>
        </div>
      </section>

      {/* All Features Grid */}
      <section className="section">
        <div className="container">
          <div className="section-header">
            <h2>Packed with Professional Tools</h2>
            <p>Everything from drawing to exporting — all in one app, no subscriptions needed.</p>
          </div>

          <div className="features-grid">
            <FeatureCard
              icon={<MdDraw />}
              color="#2196F3"
              title="Floor Plan Editor"
              desc="Draw walls, rooms, doors, and windows with intuitive touch controls. Use straight walls for precision, freehand for organic shapes, or the room tool for quick rectangles."
            />
            <FeatureCard
              icon={<MdDoorFront />}
              color="#0EA5E9"
              title="Doors & Windows"
              desc="Place 6 door types (single, double, sliding, folding, pocket, french) and windows on any wall. Drag to reposition along walls with pixel-perfect placement."
            />
            <FeatureCard
              icon={<MdChair />}
              color="#8B5CF6"
              title="Furniture Library"
              desc="Extensive catalog with beds, sofas, tables, desks, kitchen appliances, bathroom fixtures, and more. Drag, drop, rotate, and resize any item."
            />
            <FeatureCard
              icon={<MdViewInAr />}
              color="#EC4899"
              title="3D Preview"
              desc="Instant isometric 3D view of your entire plan. Rotate freely, zoom in, and see textured floors, lit walls, and 3D furniture — all rendered in real-time."
            />
            <FeatureCard
              icon={<MdStraighten />}
              color="#EF4444"
              title="Measure Ruler"
              desc="Measure distances precisely between any two points. Supports feet, meters, centimeters, inches, and millimeters. Dimensions display on walls automatically."
            />
            <FeatureCard
              icon={<MdCalculate />}
              color="#F59E0B"
              title="Construction Calculator"
              desc="Estimate material quantities for real construction — bricks, concrete, paint, tiles, and more. Enter dimensions and get accurate quantities instantly."
            />
            <FeatureCard
              icon={<MdLayers />}
              color="#06B6D4"
              title="Multi-Floor & Layers"
              desc="Design multi-story buildings with separate floor levels. Use layers to organize walls, furniture, and annotations independently."
            />
            <FeatureCard
              icon={<MdStairs />}
              color="#14B8A6"
              title="Stairs & Columns"
              desc="Add staircases to connect floors and structural columns for support. Position and size them accurately within your plan."
            />
            <FeatureCard
              icon={<MdGridOn />}
              color="#6366F1"
              title="Smart Grid & Snap"
              desc="Configurable grid overlay with snap-to-grid for precise alignment. Walls and objects snap to grid points, endpoints, and wall intersections."
            />
            <FeatureCard
              icon={<MdHistory />}
              color="#A855F7"
              title="Unlimited Undo/Redo"
              desc="Full action history lets you undo and redo any change. Experiment freely — you can always go back to any previous state."
            />
            <FeatureCard
              icon={<MdPictureAsPdf />}
              color="#14B8A6"
              title="PDF & Image Export"
              desc="Export at 300 DPI for professional print quality. Generate PDFs with measurements or high-res images for sharing with clients and contractors."
            />
            <FeatureCard
              icon={<MdBubbleChart />}
              color="#10B981"
              title="Bubble Level"
              desc="Built-in digital bubble level uses your device's accelerometer. Check if surfaces are level during construction or furniture installation."
            />
          </div>
        </div>
      </section>

      {/* Download CTA */}
      <section className="download-section" id="download">
        <div className="container">
          <h2>Start Designing Today</h2>
          <p>
            Download Floor Plan Designer for free. Design homes, offices, and any space
            with professional tools — then preview in 3D and export.
          </p>
          <div className="download-buttons">
            <a href="https://play.google.com/store/apps/details?id=com.buildsmart.homedesigns" className="store-btn" target="_blank" rel="noopener noreferrer">
              <span className="store-icon"><FaGooglePlay /></span>
              <span className="store-text">
                <small>GET IT ON</small>
                Google Play
              </span>
            </a>
            <a href="https://apps.apple.com/us/app/floor-plan-designer-home-map/id6760694900" className="store-btn" target="_blank" rel="noopener noreferrer">
              <span className="store-icon"><FaApple /></span>
              <span className="store-text">
                <small>DOWNLOAD ON THE</small>
                App Store
              </span>
            </a>
          </div>
        </div>
      </section>
    </>
  );
}

/* ========== Sub-components ========== */

const HERO_FEATURES = [
  { icon: <MdViewInAr />,    label: '3D Preview',       color: '#60A5FA' },
  { icon: <MdChair />,       label: 'Furniture Library', color: '#A78BFA' },
  { icon: <MdStraighten />,  label: 'Measure Tools',    color: '#34D399' },
  { icon: <MdPictureAsPdf />,label: 'PDF Export',       color: '#F472B6' },
  { icon: <MdGridOn />,      label: 'Smart Grid',       color: '#FBBF24' },
  { icon: <MdLayers />,      label: 'Multi-Floor',      color: '#38BDF8' },
];

const HERO_STATS = [
  { value: '9+',  label: 'Pro Tools'  },
  { value: '40+', label: 'Languages'  },
  { value: '3D',  label: 'Preview'    },
  { value: '300', label: 'DPI Export' },
];

function useCountUp(target, duration = 1200, start = false) {
  const [count, setCount] = useState(0);
  useEffect(() => {
    if (!start) return;
    const num = parseFloat(target);
    if (isNaN(num)) { setCount(target); return; }
    let startTime = null;
    const step = (ts) => {
      if (!startTime) startTime = ts;
      const progress = Math.min((ts - startTime) / duration, 1);
      setCount(Math.floor(progress * num));
      if (progress < 1) requestAnimationFrame(step);
      else setCount(target);
    };
    requestAnimationFrame(step);
  }, [start, target, duration]);
  return count;
}

function HeroSection() {
  const sectionRef = useRef(null);
  const [visible, setVisible] = useState(false);
  const [mousePos, setMousePos] = useState({ x: 0, y: 0 });

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => { if (entry.isIntersecting) setVisible(true); },
      { threshold: 0.1 }
    );
    if (sectionRef.current) observer.observe(sectionRef.current);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    const handleMouse = (e) => {
      const rect = sectionRef.current?.getBoundingClientRect();
      if (!rect) return;
      setMousePos({
        x: ((e.clientX - rect.left) / rect.width - 0.5) * 20,
        y: ((e.clientY - rect.top) / rect.height - 0.5) * 10,
      });
    };
    window.addEventListener('mousemove', handleMouse);
    return () => window.removeEventListener('mousemove', handleMouse);
  }, []);

  return (
    <section className="hero-v2" ref={sectionRef}>
      {/* Animated background layers */}
      <div className="hv2-bg">
        <div className="hv2-grid" />
        <div className="hv2-orb hv2-orb-1" />
        <div className="hv2-orb hv2-orb-2" />
        <div className="hv2-orb hv2-orb-3" />
        <div className="hv2-orb hv2-orb-4" />
        <div className="hv2-noise" />
      </div>

      {/* Floating particles */}
      {[...Array(18)].map((_, i) => (
        <div key={i} className="hv2-particle" style={{ '--i': i }} />
      ))}

      <div className="container hv2-container">
        {/* ── Left content ── */}
        <div className={`hv2-content ${visible ? 'hv2-visible' : ''}`}>
          {/* Badge */}
          <div className="hv2-badge">
            <span className="hv2-badge-dot" />
            <MdVerified className="hv2-badge-icon" />
            <span>Professional Floor Plan Tool</span>
          </div>

          {/* Headline */}
          <h1 className="hv2-h1">
            <span className="hv2-line hv2-line-1">Design Your</span>
            <span className="hv2-line hv2-line-2">
              <span className="hv2-gradient-text">Dream Space</span>
            </span>
            <span className="hv2-line hv2-line-3">in Minutes</span>
          </h1>

          {/* Sub */}
          <p className="hv2-sub">
            Create professional floor plans, add furniture, preview in 3D —
            all from your phone. From single rooms to multi-story buildings.
          </p>

          {/* Feature pills */}
          <div className="hv2-pills">
            {HERO_FEATURES.map((f, i) => (
              <div className="hv2-pill" key={i} style={{ '--pc': f.color, '--pi': i }}>
                <span className="hv2-pill-icon" style={{ color: f.color }}>{f.icon}</span>
                {f.label}
              </div>
            ))}
          </div>

          {/* CTA buttons */}
          <div className="hv2-ctas">
            <a
              href="https://play.google.com/store/apps/details?id=com.buildsmart.homedesigns"
              className="hv2-btn-primary"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span className="hv2-btn-shine" />
              <FaGooglePlay className="hv2-btn-icon" />
              <span>
                <small>GET IT ON</small>
                Google Play
              </span>
            </a>
            <a
              href="https://apps.apple.com/us/app/floor-plan-designer-home-map/id6760694900"
              className="hv2-btn-secondary"
              target="_blank"
              rel="noopener noreferrer"
            >
              <FaApple className="hv2-btn-icon" />
              <span>
                <small>DOWNLOAD ON THE</small>
                App Store
              </span>
            </a>
          </div>

          {/* Rating row */}
          <div className="hv2-rating">
            <div className="hv2-stars">
              {[...Array(5)].map((_, i) => <MdStar key={i} />)}
            </div>
            <span className="hv2-rating-text">4.8 · Trusted by designers worldwide</span>
          </div>
        </div>

        {/* ── Right — Phone visual ── */}
        <div
          className={`hv2-visual ${visible ? 'hv2-visible' : ''}`}
          style={{ '--mx': `${mousePos.x}px`, '--my': `${mousePos.y}px` }}
        >
          {/* Glow ring behind phone */}
          <div className="hv2-phone-glow" />

          {/* Floating feature badges */}
          <div className="hv2-float-badge hv2-fb-1">
            <MdViewInAr /> 3D Preview
          </div>
          <div className="hv2-float-badge hv2-fb-2">
            <MdCheckCircle style={{ color: '#34D399' }} /> Floor Plan Ready
          </div>
          <div className="hv2-float-badge hv2-fb-3">
            <MdPictureAsPdf /> Export PDF
          </div>

          {/* Phone mockup */}
          <div className="hv2-phone-wrap">
            <div className="hv2-phone-frame">
              <div className="hv2-phone-notch" />
              <div className="hv2-phone-screen">
                <img
                  src="/images/Floor-Plan-New-Screenshots-254185.jpg"
                  alt="Floor Plan Designer App"
                  className="hv2-phone-img"
                />
              </div>
              <div className="hv2-phone-home-bar" />
            </div>
          </div>

          {/* Orbit rings */}
          <div className="hv2-orbit hv2-orbit-1" />
          <div className="hv2-orbit hv2-orbit-2" />
        </div>
      </div>

      {/* Stats bar */}
      <div className={`hv2-stats ${visible ? 'hv2-visible' : ''}`}>
        <div className="container hv2-stats-inner">
          {HERO_STATS.map((s, i) => (
            <React.Fragment key={i}>
              <div className="hv2-stat">
                <strong>{s.value}</strong>
                <span>{s.label}</span>
              </div>
              {i < HERO_STATS.length - 1 && <div className="hv2-stat-div" />}
            </React.Fragment>
          ))}
        </div>
      </div>

      {/* Scroll indicator */}
      <div className="hv2-scroll-hint">
        <MdKeyboardArrowDown />
      </div>
    </section>
  );
}

function DesignSection() {
  const sectionRef = useRef(null);
  const headerRef = useRef(null);
  const cardRefs = useRef([]);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('anim-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );

    if (headerRef.current) observer.observe(headerRef.current);
    cardRefs.current.forEach((el) => { if (el) observer.observe(el); });

    return () => observer.disconnect();
  }, []);

  const plans = [
    {
      icon: <MdHome />, color: '#2196F3', title: 'Home Floor Plans',
      desc: 'Design complete house layouts with bedrooms, living rooms, kitchens, and bathrooms. Plan furniture placement and room flow before building or renovating.',
      rooms: ['Living Room', 'Bedroom', 'Kitchen', 'Bathroom', 'Garage'],
    },
    {
      icon: <MdApartment />, color: '#8B5CF6', title: 'Apartment Layouts',
      desc: 'Plan studio, 1-bedroom, or multi-bedroom apartment layouts. Optimize space in compact living areas with precise measurements.',
      rooms: ['Studio', '1-BHK', '2-BHK', 'Penthouse', 'Duplex'],
    },
    {
      icon: <MdBusiness />, color: '#F59E0B', title: 'Office & Commercial',
      desc: 'Create office floor plans with workstations, meeting rooms, and reception areas. Design retail stores, restaurants, and commercial spaces.',
      rooms: ['Open Office', 'Meeting Room', 'Reception', 'Break Room', 'Storage'],
    },
    {
      icon: <MdMeetingRoom />, color: '#10B981', title: 'Single Room Design',
      desc: 'Focus on one room at a time. Perfect for redesigning a bedroom, planning a kitchen remodel, or rearranging a living room.',
      rooms: ['Bedroom', 'Kitchen', 'Living Room', 'Study', 'Nursery'],
    },
    {
      icon: <MdKitchen />, color: '#EF4444', title: 'Kitchen & Bath Plans',
      desc: 'Detailed kitchen and bathroom layouts with appliances, cabinets, fixtures, and plumbing placement. Measure every inch for a perfect fit.',
      rooms: ['L-Shape Kitchen', 'U-Shape Kitchen', 'Master Bath', 'Powder Room'],
    },
    {
      icon: <MdWeekend />, color: '#06B6D4', title: 'Interior Design',
      desc: 'Go beyond walls — furnish every room with sofas, beds, tables, desks, and more from the built-in furniture catalog. Visualize before you buy.',
      rooms: ['Furniture Layout', 'Color Scheme', 'Space Planning', 'Decor'],
    },
  ];

  return (
    <section className="section section-alt design-section" ref={sectionRef}>
      <div className="design-section-bg">
        <div className="design-orb orb-1" />
        <div className="design-orb orb-2" />
        <div className="design-orb orb-3" />
      </div>
      <div className="container" style={{ position: 'relative', zIndex: 1 }}>
        <div className="section-header design-header anim-fade-up" ref={headerRef}>
          <div className="design-eyebrow">What You Can Build</div>
          <h2>Design Any Space <span>You Can Imagine</span></h2>
          <p>
            Whether you're planning a home renovation, designing a new office layout,
            or creating architectural plans — Floor Plan Designer handles it all.
          </p>
        </div>

        <div className="plan-types-grid">
          {plans.map((plan, i) => (
            <div
              key={i}
              className="plan-type-card feature-card anim-card anim-fade-up"
              ref={(el) => (cardRefs.current[i] = el)}
              style={{ '--card-delay': `${i * 0.1}s`, '--card-color': plan.color }}
            >
              <div className="design-card-glow" />
              <div className="feature-icon design-icon" style={{ background: `${plan.color}18`, color: plan.color }}>
                {plan.icon}
                <div className="icon-ring" style={{ borderColor: `${plan.color}40` }} />
              </div>
              <h3>{plan.title}</h3>
              <p>{plan.desc}</p>
              <div className="plan-tags">
                {plan.rooms.map((room, j) => (
                  <span
                    key={j}
                    className="plan-tag"
                    style={{ background: `${plan.color}12`, color: plan.color, '--tag-delay': `${j * 0.05}s` }}
                  >
                    {room}
                  </span>
                ))}
              </div>
              <div className="card-shine" />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function FeatureCard({ icon, color, title, desc }) {
  return (
    <div className="feature-card">
      <div className="feature-icon" style={{ background: `${color}15`, color }}>
        {icon}
      </div>
      <h3>{title}</h3>
      <p>{desc}</p>
    </div>
  );
}

function PlanTypeCard({ icon, color, title, desc, rooms }) {
  return (
    <div className="feature-card plan-type-card">
      <div className="feature-icon" style={{ background: `${color}15`, color }}>
        {icon}
      </div>
      <h3>{title}</h3>
      <p>{desc}</p>
      <div className="plan-tags">
        {rooms.map((room, i) => (
          <span key={i} className="plan-tag" style={{ background: `${color}10`, color }}>{room}</span>
        ))}
      </div>
    </div>
  );
}

function StepCard({ number, title, desc }) {
  return (
    <div className="step-card">
      <div className="step-number">{number}</div>
      <h3>{title}</h3>
      <p>{desc}</p>
    </div>
  );
}

function Preview3DFeature({ icon, color, title, desc }) {
  return (
    <div className="preview-feature-item">
      <div className="preview-feature-icon" style={{ background: `${color}15`, color }}>
        {icon}
      </div>
      <div>
        <h4>{title}</h4>
        <p>{desc}</p>
      </div>
    </div>
  );
}

function TechBadge({ label }) {
  return <span className="tech-badge">{label}</span>;
}
