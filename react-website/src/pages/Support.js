import React, { useState, useMemo, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import {
  MdEmail, MdQuestionAnswer, MdBuild, MdMenuBook,
  MdKeyboardArrowDown, MdSearch, MdDraw, MdViewInAr,
  MdStraighten, MdSave, MdChair, MdHome,
  MdSpeed, MdBugReport, MdPhoneAndroid, MdUpdate,
  MdTipsAndUpdates, MdPlayCircle, MdCheckCircle,
  MdLightbulbOutline, MdArrowForward, MdCalculate,
  MdBubbleChart, MdPictureAsPdf, MdGridOn, MdLayers,
  MdArrowOutward, MdStar, MdVerified,
} from 'react-icons/md';
import { FaGooglePlay, FaApple } from 'react-icons/fa';

const faqCategories = [
  { key: 'all', label: 'All', icon: <MdQuestionAnswer /> },
  { key: 'getting-started', label: 'Getting Started', icon: <MdDraw /> },
  { key: 'drawing', label: 'Drawing & Editing', icon: <MdHome /> },
  { key: 'measurements', label: 'Measurements', icon: <MdStraighten /> },
  { key: '3d-preview', label: '3D Preview', icon: <MdViewInAr /> },
  { key: 'plan-types', label: 'Plan Types', icon: <MdHome /> },
  { key: 'saving', label: 'Saving & Export', icon: <MdSave /> },
];

const faqs = [
  {
    category: 'getting-started',
    categoryLabel: 'Getting Started',
    icon: <MdDraw />,
    color: '#2196F3',
    items: [
      {
        q: 'How do I create a new floor plan?',
        a: 'Tap the "Draw" button on the home screen to open the floor plan editor. You can start with a blank canvas and use the drawing tools to create walls, add doors, windows, and furniture.',
      },
      {
        q: 'What drawing tools are available?',
        a: 'The app includes Straight Wall, Freehand Wall, Room (rectangular), Door, Window, Stairs, Furniture, and Measure tools. Select any tool from the bottom toolbar in the editor.',
      },
      {
        q: 'How do I navigate the canvas?',
        a: 'Pinch with two fingers to zoom in/out. Drag with one finger (when the Pan tool is selected) to move around the canvas. Double-tap to reset the view.',
      },
      {
        q: 'What is the grid and how do I use it?',
        a: 'The grid is an overlay of evenly spaced lines on the canvas that helps you align walls and objects. Enable it in Settings. With "Snap to Grid" turned on, walls and objects automatically align to the nearest grid point for precise placement.',
      },
    ],
  },
  {
    category: 'drawing',
    categoryLabel: 'Drawing & Editing',
    icon: <MdHome />,
    color: '#8B5CF6',
    items: [
      {
        q: 'How do I add doors and windows?',
        a: 'Select the Door or Window tool from the toolbar, then tap on a wall where you want to place it. You can drag it along the wall to reposition. Choose from different types: single, double, sliding, folding, pocket, or french doors.',
      },
      {
        q: 'Can I add furniture to my floor plan?',
        a: 'Yes! Tap the Furniture tool to open the furniture catalog. Browse categories including beds, sofas, tables, desks, kitchen appliances, and bathroom fixtures. Select an item, tap on the canvas to place it, then move, rotate, and resize as needed.',
      },
      {
        q: 'How do I undo a mistake?',
        a: 'Use the Undo button in the editor toolbar to reverse your last action. You can also use Redo to restore undone changes. The app maintains a complete history of your actions — undo as many steps as needed.',
      },
      {
        q: 'Can I work with multiple floors?',
        a: 'Yes, the app supports multiple floor levels. You can add new floors and switch between them to design multi-story buildings. Add stairs to visually connect levels.',
      },
      {
        q: 'How do I use layers?',
        a: 'Layers let you organize different elements of your design. You can separate walls, furniture, and annotations onto different layers, then show/hide or lock individual layers for easier editing.',
      },
      {
        q: 'How do I select and move objects?',
        a: 'Switch to the Select tool (arrow icon), then tap on any object to select it. Drag to move it, use handles to resize, or use the rotation control to rotate. Tap empty space to deselect.',
      },
    ],
  },
  {
    category: 'measurements',
    categoryLabel: 'Measurements & Tools',
    icon: <MdStraighten />,
    color: '#EF4444',
    items: [
      {
        q: 'How do I measure distances?',
        a: 'Select the Measure tool from the toolbar. Tap two points on the canvas to see the distance between them. Measurements display in your chosen unit system.',
      },
      {
        q: 'What measurement units are supported?',
        a: 'The app supports feet, meters, centimeters, inches, and millimeters. You can change the default unit in Settings at any time. All displayed measurements update automatically when you switch units.',
      },
      {
        q: 'What does the Construction Calculator do?',
        a: 'The Construction Calculator helps you estimate material quantities for building projects. Calculate bricks, concrete, paint, tiles, and more based on dimensions you provide. Essential for budgeting and planning.',
      },
      {
        q: 'How does the Bubble Level work?',
        a: "The Bubble Level uses your device's accelerometer to detect surface inclination. Place your device on a surface to check if it is level — useful during construction and installation work.",
      },
      {
        q: 'How does the Area Calculator work?',
        a: 'The Area Calculator lets you calculate areas and volumes for various shapes — rectangles, circles, triangles, and more. Useful for estimating room areas, tank capacity, and material needs.',
      },
    ],
  },
  {
    category: '3d-preview',
    categoryLabel: '3D Preview',
    icon: <MdViewInAr />,
    color: '#EC4899',
    items: [
      {
        q: 'How do I view my floor plan in 3D?',
        a: 'Open your floor plan in the editor or preview screen, then tap the 3D button (cube icon) in the toolbar. Your entire plan — walls, doors, windows, furniture — will render as an interactive 3D isometric view.',
      },
      {
        q: 'How do I navigate the 3D view?',
        a: 'Drag with one finger to rotate the 3D model in any direction. The view has smooth inertia, so it keeps spinning gently after a swipe. Pinch with two fingers to zoom in (up to 4x) or out (down to 0.4x).',
      },
      {
        q: 'What is rendered in the 3D preview?',
        a: 'Everything in your plan renders in 3D: walls with correct height, doors and windows as openings cut into walls, furniture as 3D objects (beds, sofas, tables, toilets, sinks, stairs, etc.), and textured floors (terrazzo, concrete, wood, marble, grass). Walls also have directional lighting so different sides have different shading.',
      },
      {
        q: 'Does 3D preview require an internet connection?',
        a: 'No. All 3D rendering happens locally on your device in real-time. No data is uploaded and no internet is needed. It works completely offline.',
      },
      {
        q: 'Why do my floor textures look different when I rotate?',
        a: 'Floor textures use world-anchored UV coordinates, meaning they stay stable as you rotate the model. If textures appear inconsistent, try ensuring you have the latest version of the app installed.',
      },
      {
        q: 'Can I export the 3D view?',
        a: 'Currently, export produces 2D floor plan PDFs and images. The 3D view is for interactive visualization within the app. We may add 3D export in future updates.',
      },
    ],
  },
  {
    category: 'plan-types',
    categoryLabel: 'Floor Plan Types & Design',
    icon: <MdHome />,
    color: '#10B981',
    items: [
      {
        q: 'What types of floor plans can I create?',
        a: 'You can design virtually any space: houses, apartments, offices, commercial buildings, single rooms, kitchens, bathrooms, studios, and more. The app supports multi-room layouts with corridors, open floor plans, and multi-story buildings.',
      },
      {
        q: 'Can I design multi-story buildings?',
        a: 'Yes! Use the floor/level feature to add multiple stories. Each floor has its own independent layout. Add stairs to connect levels. Switch between floors to design each one separately.',
      },
      {
        q: 'What door and window types are available?',
        a: 'The app supports 6 door swing types: single, double, sliding, folding, pocket, and french doors. Windows can be placed on any wall and repositioned by dragging. Both doors and windows render correctly in the 3D preview with proper openings.',
      },
      {
        q: 'Can I use the app for professional/commercial floor plans?',
        a: 'The app is excellent for planning and visualization. However, floor plans are intended for reference and planning purposes. For construction-grade architectural drawings, consult a licensed architect or engineer. The app is perfect for initial design, client presentations, and space planning.',
      },
      {
        q: 'How accurate are the measurements?',
        a: 'Measurements are accurate to the grid resolution you configure. With snap-to-grid enabled, elements align precisely. The app supports multiple units (feet, meters, cm, inches, mm). Dimensions display on walls and objects for easy reference. Export at 300 DPI preserves measurement accuracy.',
      },
    ],
  },
  {
    category: 'saving',
    categoryLabel: 'Saving & Exporting',
    icon: <MdSave />,
    color: '#F59E0B',
    items: [
      {
        q: 'Are my projects saved automatically?',
        a: 'Yes, if auto-save is enabled in Settings, your work is saved periodically (default: every 5 minutes). Projects are also saved when you exit the editor.',
      },
      {
        q: 'How do I export my floor plan?',
        a: 'Use the Export feature from the home screen menu. You can export your design as a high-quality PDF or image file at 300 DPI, suitable for printing or sharing.',
      },
      {
        q: "Where are my saved projects stored?",
        a: "All projects are stored locally on your device in the app's document directory. Your designs are never uploaded to external servers unless you choose to share them.",
      },
      {
        q: 'Can I share my designs?',
        a: 'Yes, after exporting your floor plan as a PDF or image, you can share it through any sharing method available on your device (email, messaging, cloud storage, etc.).',
      },
      {
        q: 'Can I rename or duplicate projects?',
        a: 'Yes. From the Saved Plans screen, tap the menu icon on any project to see options including Rename, Duplicate, Share, and Delete.',
      },
    ],
  },
];

const quickGuides = [
  {
    icon: <MdDraw />,
    color: '#2196F3',
    title: 'Draw Your First Room',
    steps: [
      'Tap "Draw" on the home screen',
      'Select the Room tool from the toolbar',
      'Tap and drag on the canvas to draw a rectangle',
      'Release to complete the room — walls are created automatically',
    ],
  },
  {
    icon: <MdChair />,
    color: '#8B5CF6',
    title: 'Add Furniture',
    steps: [
      'Select the Furniture tool from the toolbar',
      'Browse categories or search for an item',
      'Tap an item to select it',
      'Tap on the canvas to place, then drag to reposition',
    ],
  },
  {
    icon: <MdViewInAr />,
    color: '#EC4899',
    title: 'Preview in 3D',
    steps: [
      'Open the editor with your floor plan',
      'Tap the 3D cube icon in the toolbar',
      'Drag to rotate the 3D model',
      'Pinch to zoom in/out — explore from any angle',
    ],
  },
  {
    icon: <MdPictureAsPdf />,
    color: '#14B8A6',
    title: 'Export Your Plan',
    steps: [
      'From the home screen, tap "Export"',
      'Select your project',
      'Choose PDF or Image format',
      'Save to your device or share directly',
    ],
  },
];

const proTips = [
  { icon: <MdGridOn />, color: '#2196F3', tip: 'Enable Snap to Grid in Settings for pixel-perfect wall alignment.' },
  { icon: <MdLayers />, color: '#8B5CF6', tip: 'Use layers to separate furniture from walls — hide furniture to see the structure clearly.' },
  { icon: <MdViewInAr />, color: '#EC4899', tip: 'Check 3D preview frequently while designing to catch layout issues early.' },
  { icon: <MdStraighten />, color: '#EF4444', tip: 'Measure doorways and hallways — aim for at least 0.9m (3ft) width for comfortable passage.' },
  { icon: <MdCalculate />, color: '#F59E0B', tip: 'Use the Construction Calculator before buying materials to avoid waste.' },
  { icon: <MdBubbleChart />, color: '#10B981', tip: 'The Bubble Level works best when placed on a flat surface with the screen facing up.' },
];

function useScrollReveal(threshold = 0.12) {
  const ref = useRef(null);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      ([entry]) => { if (entry.isIntersecting) { el.classList.add('sp-visible'); observer.unobserve(el); } },
      { threshold }
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [threshold]);
  return ref;
}

export default function Support() {
  const [searchQuery, setSearchQuery] = useState('');
  const [activeCategory, setActiveCategory] = useState('all');

  const filteredFaqs = useMemo(() => {
    const query = searchQuery.toLowerCase().trim();
    return faqs
      .filter((section) => activeCategory === 'all' || section.category === activeCategory)
      .map((section) => ({
        ...section,
        items: section.items.filter(
          (item) =>
            !query ||
            item.q.toLowerCase().includes(query) ||
            item.a.toLowerCase().includes(query)
        ),
      }))
      .filter((section) => section.items.length > 0);
  }, [searchQuery, activeCategory]);

  const totalResults = filteredFaqs.reduce((sum, s) => sum + s.items.length, 0);
  const totalFaqs = faqs.reduce((s, c) => s + c.items.length, 0);

  return (
    <>
      {/* ── Hero ── */}
      <section className="sp-hero">
        <div className="sp-hero-bg">
          <div className="sp-orb sp-orb-1" />
          <div className="sp-orb sp-orb-2" />
          <div className="sp-orb sp-orb-3" />
          <div className="sp-grid-lines" />
        </div>
        <div className="container sp-hero-inner">
          <div className="sp-hero-badge">
            <MdVerified /> Support Center
          </div>
          <h1>How Can We <span className="sp-gradient-text">Help You?</span></h1>
          <p>Find answers, guides, and tips for Floor Plan Designer — all in one place.</p>

          <div className="sp-search-wrap">
            <MdSearch className="sp-search-icon" />
            <input
              type="text"
              className="sp-search-input"
              placeholder='Search — e.g. "3D preview", "export PDF", "add door"...'
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
            {searchQuery && (
              <button className="sp-search-clear" onClick={() => setSearchQuery('')}>✕</button>
            )}
          </div>

          <div className="sp-hero-stats">
            <div className="sp-hero-stat"><strong>{totalFaqs}</strong><span>Answers</span></div>
            <div className="sp-hero-stat-divider" />
            <div className="sp-hero-stat"><strong>{faqs.length}</strong><span>Categories</span></div>
            <div className="sp-hero-stat-divider" />
            <div className="sp-hero-stat"><strong>24h</strong><span>Response Time</span></div>
            <div className="sp-hero-stat-divider" />
            <div className="sp-hero-stat"><strong>40+</strong><span>Languages</span></div>
          </div>
        </div>
      </section>

      {/* ── Quick Help Cards ── */}
      <section className="section sp-cards-section">
        <div className="container">
          <ScrollRevealGrid className="sp-quick-grid">
            <QuickCard icon={<MdMenuBook />} color="#2196F3" title="Quick Start Guides" desc="Step-by-step walkthroughs for common tasks." link="#guides" index={0} />
            <QuickCard icon={<MdQuestionAnswer />} color="#10B981" title="FAQ" desc={`${totalFaqs} answers across ${faqs.length} categories.`} link="#faq" index={1} />
            <QuickCard icon={<MdBuild />} color="#F59E0B" title="Troubleshooting" desc="Quick fixes for common issues and errors." link="#troubleshooting" index={2} />
            <QuickCard icon={<MdTipsAndUpdates />} color="#EC4899" title="Pro Tips" desc="Expert tips to get the most out of every feature." link="#tips" index={3} />
            <QuickCard icon={<MdPlayCircle />} color="#8B5CF6" title="Video Tutorials" desc="Watch visual guides for complex features." link="#videos" index={4} />
            <QuickCard icon={<MdEmail />} color="#6366F1" title="Contact Support" desc="Can't find your answer? Our team will help." link="/contact" isInternal index={5} />
          </ScrollRevealGrid>
        </div>
      </section>

      {/* ── Quick Start Guides ── */}
      <section className="section section-alt" id="guides">
        <div className="container">
          <SectionHeader
            eyebrow="Step by Step"
            title="Quick Start Guides"
            subtitle="Follow these step-by-step guides to master the app in minutes."
          />
          <div className="sp-guides-grid">
            {quickGuides.map((guide, i) => (
              <GuideCard key={i} {...guide} index={i} />
            ))}
          </div>
        </div>
      </section>

      {/* ── Video Tutorials ── */}
      <section className="section" id="videos">
        <div className="container">
          <SectionHeader
            eyebrow="Watch & Learn"
            title="Video Tutorials"
            subtitle="Visual walkthroughs for key features — watch and follow along."
          />
          <div className="sp-video-grid">
            <VideoCard color="#2196F3" title="Getting Started — Your First Floor Plan" duration="3 min" desc="Learn the basics: open the editor, draw walls, add rooms, and save your project." index={0} />
            <VideoCard color="#EC4899" title="3D Preview Deep Dive" duration="2 min" desc="Explore the 3D view: rotate, zoom, and understand how textures and lighting work." index={1} />
            <VideoCard color="#8B5CF6" title="Furnishing Your Space" duration="4 min" desc="Browse the furniture catalog, place items, and arrange your room for the perfect layout." index={2} />
            <VideoCard color="#14B8A6" title="Exporting & Sharing Designs" duration="2 min" desc="Export to PDF or image at 300 DPI and share with clients or contractors." index={3} />
          </div>
        </div>
      </section>

      {/* ── FAQ ── */}
      <section className="section section-alt" id="faq">
        <div className="container">
          <SectionHeader
            eyebrow="Frequently Asked"
            title="Have Questions?"
            subtitle="Everything you need to know about Floor Plan Designer."
          />

          <div className="sp-faq-tabs">
            {faqCategories.map((cat) => (
              <button
                key={cat.key}
                className={`sp-faq-tab ${activeCategory === cat.key ? 'active' : ''}`}
                onClick={() => setActiveCategory(cat.key)}
              >
                <span className="sp-faq-tab-icon">{cat.icon}</span>
                {cat.label}
              </button>
            ))}
          </div>

          {searchQuery && (
            <div className="sp-results-banner">
              <MdSearch />
              Found <strong>{totalResults}</strong> result{totalResults !== 1 ? 's' : ''} for "<em>{searchQuery}</em>"
              {activeCategory !== 'all' && <> in <strong>{faqCategories.find(c => c.key === activeCategory)?.label}</strong></>}
            </div>
          )}

          {filteredFaqs.length > 0 ? (
            filteredFaqs.map((section, i) => (
              <div key={i} className="sp-faq-group">
                <div className="sp-faq-group-header" style={{ '--gc': section.color }}>
                  <span className="sp-faq-group-icon" style={{ background: `${section.color}15`, color: section.color }}>
                    {section.icon}
                  </span>
                  <h3>{section.categoryLabel}</h3>
                  <span className="sp-faq-count" style={{ background: `${section.color}12`, color: section.color }}>
                    {section.items.length}
                  </span>
                </div>
                <div className="sp-faq-list">
                  {section.items.map((faq, j) => (
                    <FaqItem key={j} question={faq.q} answer={faq.a} highlight={searchQuery} color={section.color} />
                  ))}
                </div>
              </div>
            ))
          ) : (
            <div className="sp-faq-empty">
              <div className="sp-faq-empty-icon"><MdSearch /></div>
              <h3>No results found</h3>
              <p>Try a different search term or browse all categories.</p>
              <button className="btn btn-outline" onClick={() => { setSearchQuery(''); setActiveCategory('all'); }}>
                Clear Filters
              </button>
            </div>
          )}
        </div>
      </section>

      {/* ── Troubleshooting ── */}
      <section className="section" id="troubleshooting">
        <div className="container">
          <SectionHeader
            eyebrow="Common Issues"
            title="Troubleshooting"
            subtitle="Quick fixes to get you back on track."
          />
          <div className="sp-trouble-grid">
            <TroubleshootCard icon={<MdSpeed />} color="#F59E0B" title="App Running Slowly?" tips={['Clear cache from Settings > Save & Storage > Clear Cache.', 'Close other apps running in the background.', 'Ensure your device has at least 500MB free storage.', 'Complex plans with many furniture items may slow older devices — try hiding layers.']} />
            <TroubleshootCard icon={<MdSave />} color="#EF4444" title="Project Not Saving?" tips={['Check that auto-save is enabled in Settings.', 'Ensure the app has storage permissions in your device settings.', 'Try manually exporting the project as a backup.', 'Restart the app if the save indicator is stuck.']} />
            <TroubleshootCard icon={<MdPictureAsPdf />} color="#6366F1" title="Export Not Working?" tips={['Make sure your project has content to export.', 'Check that the app has file access permissions.', 'Try restarting the app and exporting again.', 'Ensure you have enough storage space for the exported file.']} />
            <TroubleshootCard icon={<MdViewInAr />} color="#EC4899" title="3D Preview Issues?" tips={['Ensure your plan has at least one room or wall before switching to 3D.', 'If textures appear missing, close and reopen the 3D view.', 'Zoom out if the view appears blank — your plan may be outside the visible area.', 'Updating the app to the latest version fixes most rendering issues.']} />
            <TroubleshootCard icon={<MdPhoneAndroid />} color="#10B981" title="App Crashing?" tips={['Update to the latest version from Google Play or the App Store.', 'Clear the app cache (Settings > Save & Storage > Clear Cache).', 'Restart your device if crashes persist.', 'Report the issue via Contact Us with your device model and OS version.']} />
            <TroubleshootCard icon={<MdBugReport />} color="#0EA5E9" title="Found a Bug?" tips={['Note the steps that led to the issue.', 'Take a screenshot if possible.', 'Go to Contact Us and select "Bug Report" as the subject.', 'Include your device model, OS version, and app version.']} />
          </div>
        </div>
      </section>

      {/* ── Pro Tips ── */}
      <section className="section section-alt" id="tips">
        <div className="container">
          <SectionHeader
            eyebrow="Expert Advice"
            title="Pro Tips"
            subtitle="Get the most out of every feature with these expert shortcuts."
          />
          <div className="sp-tips-grid">
            {proTips.map((item, i) => (
              <TipCard key={i} icon={item.icon} color={item.color} tip={item.tip} index={i} />
            ))}
          </div>
        </div>
      </section>

      {/* ── App Info Bar ── */}
      <section className="section">
        <div className="container">
          <div className="sp-info-bar">
            <InfoItem icon={<MdUpdate />} color="#2196F3" label="Latest Version" value="1.0.1" />
            <div className="sp-info-divider" />
            <InfoItem icon={<MdPhoneAndroid />} color="#10B981" label="Platforms" value="Android & iOS" />
            <div className="sp-info-divider" />
            <InfoItem icon={<MdStar />} color="#F59E0B" label="Rating" value="4.8 / 5 Stars" />
            <div className="sp-info-divider" />
            <InfoItem icon={<MdLightbulbOutline />} color="#8B5CF6" label="Languages" value="40+ Supported" />
          </div>
        </div>
      </section>

      {/* ── Download CTA ── */}
      <section className="section section-alt">
        <div className="container">
          <div className="sp-download-cta">
            <div className="sp-download-cta-bg" />
            <div className="sp-download-cta-content">
              <div className="sp-download-cta-badge"><MdCheckCircle /> Free to Download</div>
              <h2>Still Need Help?</h2>
              <p>Our support team is ready to assist you. Send us a message and we'll get back to you within 24–48 hours. Or download the app and explore it yourself.</p>
              <div className="sp-download-cta-actions">
                <Link to="/contact" className="btn btn-primary">
                  <MdEmail /> Contact Support
                </Link>
                <a href="https://play.google.com/store/apps/details?id=com.buildsmart.homedesigns" className="sp-store-pill" target="_blank" rel="noopener noreferrer">
                  <FaGooglePlay /> Google Play
                </a>
                <a href="https://apps.apple.com/us/app/floor-plan-designer-home-map/id6760694900" className="sp-store-pill sp-store-pill-dark" target="_blank" rel="noopener noreferrer">
                  <FaApple /> App Store
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}

/* ── Helpers ── */

function SectionHeader({ eyebrow, title, subtitle }) {
  const ref = useScrollReveal();
  return (
    <div className="sp-section-header sp-reveal" ref={ref}>
      {eyebrow && <div className="sp-eyebrow">{eyebrow}</div>}
      <h2>{title}</h2>
      {subtitle && <p>{subtitle}</p>}
    </div>
  );
}

function ScrollRevealGrid({ children, className }) {
  const ref = useRef(null);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const items = el.querySelectorAll('.sp-reveal-item');
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('sp-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1 }
    );
    items.forEach((item) => observer.observe(item));
    return () => observer.disconnect();
  }, []);
  return <div className={className} ref={ref}>{children}</div>;
}

function QuickCard({ icon, color, title, desc, link, isInternal, index }) {
  const content = (
    <div className="sp-quick-card sp-reveal-item" style={{ '--delay': `${index * 0.07}s`, '--qc': color }}>
      <div className="sp-quick-card-shine" />
      <div className="sp-quick-icon" style={{ background: `${color}15`, color }}>{icon}</div>
      <h3>{title}</h3>
      <p>{desc}</p>
      <div className="sp-quick-arrow" style={{ color }}><MdArrowOutward /></div>
    </div>
  );
  if (isInternal) return <Link to={link} style={{ textDecoration: 'none' }}>{content}</Link>;
  return <a href={link} style={{ textDecoration: 'none' }}>{content}</a>;
}

function GuideCard({ icon, color, title, steps, index }) {
  const ref = useScrollReveal(0.1);
  return (
    <div className="sp-guide-card sp-reveal" ref={ref} style={{ '--delay': `${index * 0.1}s`, '--gc': color }}>
      <div className="sp-guide-header">
        <div className="sp-guide-icon" style={{ background: `${color}18`, color }}>{icon}</div>
        <h3>{title}</h3>
      </div>
      <ol className="sp-guide-steps">
        {steps.map((step, i) => (
          <li key={i} style={{ '--sc': color }}>
            <span className="sp-step-num" style={{ background: color }}>{i + 1}</span>
            <span>{step}</span>
          </li>
        ))}
      </ol>
    </div>
  );
}

function VideoCard({ color, title, duration, desc, index }) {
  const ref = useScrollReveal(0.1);
  return (
    <div className="sp-video-card sp-reveal" ref={ref} style={{ '--delay': `${index * 0.08}s` }}>
      <div className="sp-video-thumb" style={{ background: `linear-gradient(135deg, ${color}25, ${color}08)`, '--vc': color }}>
        <div className="sp-play-btn" style={{ color, borderColor: `${color}40` }}>
          <MdPlayCircle />
        </div>
        <div className="sp-video-duration">{duration}</div>
      </div>
      <div className="sp-video-info">
        <h4>{title}</h4>
        <p>{desc}</p>
      </div>
    </div>
  );
}

function TroubleshootCard({ icon, color, title, tips }) {
  const ref = useScrollReveal(0.08);
  return (
    <div className="sp-trouble-card sp-reveal" ref={ref}>
      <div className="sp-trouble-top" style={{ '--tc': color }}>
        <div className="sp-trouble-icon" style={{ background: `${color}15`, color }}>{icon}</div>
        <h3>{title}</h3>
      </div>
      <ul className="sp-trouble-list">
        {tips.map((tip, i) => (
          <li key={i}>
            <MdCheckCircle style={{ color }} />
            <span>{tip}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function TipCard({ icon, color, tip, index }) {
  const ref = useScrollReveal(0.1);
  return (
    <div className="sp-tip-card sp-reveal" ref={ref} style={{ '--delay': `${index * 0.06}s`, '--tc': color }}>
      <div className="sp-tip-icon" style={{ background: `${color}15`, color }}>{icon}</div>
      <p>{tip}</p>
    </div>
  );
}

function InfoItem({ icon, color, label, value }) {
  return (
    <div className="sp-info-item">
      <div className="sp-info-icon" style={{ background: `${color}15`, color }}>{icon}</div>
      <div>
        <span className="sp-info-label">{label}</span>
        <strong className="sp-info-value">{value}</strong>
      </div>
    </div>
  );
}

function FaqItem({ question, answer, highlight, color }) {
  const [open, setOpen] = useState(false);

  const highlightText = (text) => {
    if (!highlight || !highlight.trim()) return text;
    const regex = new RegExp(`(${highlight.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    const parts = text.split(regex);
    return parts.map((part, i) =>
      regex.test(part) ? <mark key={i} className="sp-highlight">{part}</mark> : part
    );
  };

  return (
    <div className={`sp-faq-item ${open ? 'open' : ''}`} style={{ '--fc': color }}>
      <button className="sp-faq-q" onClick={() => setOpen(!open)}>
        <span>{highlightText(question)}</span>
        <span className="sp-faq-arrow"><MdKeyboardArrowDown /></span>
      </button>
      <div className="sp-faq-a">
        <div className="sp-faq-a-inner">{highlightText(answer)}</div>
      </div>
    </div>
  );
}
