-- Update About Us page with professional content
-- This will create or update the about-us page

INSERT INTO cms_pages (title, slug, content, status, seo_title, seo_description, created_at, updated_at)
VALUES (
    'About Us',
    'about-us',
    '<div class="about-us-content" style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
        <!-- Hero Section -->
        <div style="text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; border-radius: 16px; margin-bottom: 4rem; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
            <h1 style="font-size: 3.5rem; font-weight: 700; margin-bottom: 1.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">About Kari Boreholes</h1>
            <p style="font-size: 1.5rem; opacity: 0.95; max-width: 800px; margin: 0 auto; line-height: 1.6;">Delivering Excellence in Water Solutions Across Ghana</p>
        </div>

        <!-- Mission Statement -->
        <section style="margin-bottom: 4rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; margin-bottom: 3rem;">
                <div>
                    <h2 style="font-size: 2.5rem; color: #1e293b; margin-bottom: 1.5rem; font-weight: 700;">Our Mission</h2>
                    <p style="font-size: 1.15rem; line-height: 1.8; color: #475569; margin-bottom: 1.5rem;">
                        At Kari Boreholes and Civil Engineering Works, we are committed to providing reliable, sustainable water solutions that transform communities across Ghana. Our mission is to ensure every community has access to clean, safe water through professional borehole drilling, advanced water systems, and comprehensive engineering services.
                    </p>
                    <p style="font-size: 1.15rem; line-height: 1.8; color: #475569;">
                        We combine decades of experience with cutting-edge technology, including our proprietary ABBIS (Advanced Borehole Business Intelligence System), to deliver projects that exceed expectations and stand the test of time.
                    </p>
                </div>
                <div style="background: #f1f5f9; padding: 3rem; border-radius: 16px; border-left: 5px solid #0ea5e9;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">💧</div>
                    <h3 style="font-size: 1.5rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600;">Water for Life</h3>
                    <p style="color: #64748b; line-height: 1.7;">Every project we complete brings us closer to our vision: a Ghana where clean water is accessible to all.</p>
                </div>
            </div>
        </section>

        <!-- What We Do -->
        <section style="margin-bottom: 4rem;">
            <h2 style="font-size: 2.5rem; color: #1e293b; margin-bottom: 2rem; text-align: center; font-weight: 700;">What We Do</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
                <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 4px solid #0ea5e9; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🕳️</div>
                    <h3 style="font-size: 1.5rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600;">Borehole Drilling</h3>
                    <p style="color: #64748b; line-height: 1.7;">Professional borehole drilling services using state-of-the-art equipment. We handle everything from site assessment to completion, ensuring optimal water yield and quality.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 4px solid #10b981; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                    <h3 style="font-size: 1.5rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600;">Geophysical Survey</h3>
                    <p style="color: #64748b; line-height: 1.7;">Advanced geophysical surveys to identify optimal drilling locations. Our expert team uses cutting-edge technology to ensure successful water source identification.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 4px solid #f59e0b; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚙️</div>
                    <h3 style="font-size: 1.5rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600;">Pump Installation</h3>
                    <p style="color: #64748b; line-height: 1.7;">Complete pump installation and automation systems. From submersible pumps to smart control systems, we ensure efficient water delivery.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 4px solid #8b5cf6; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">💧</div>
                    <h3 style="font-size: 1.5rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600;">Water Treatment</h3>
                    <p style="color: #64748b; line-height: 1.7;">Comprehensive water treatment solutions including filtration, reverse osmosis, and UV purification. We ensure your water is safe and clean.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 4px solid #ef4444; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔧</div>
                    <h3 style="font-size: 1.5rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600;">Maintenance & Repair</h3>
                    <p style="color: #64748b; line-height: 1.7;">Regular maintenance, rehabilitation, and repair services to keep your water systems running efficiently. We offer comprehensive maintenance programs.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 4px solid #06b6d4; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🏗️</div>
                    <h3 style="font-size: 1.5rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600;">Civil Engineering</h3>
                    <p style="color: #64748b; line-height: 1.7;">Full-service civil engineering works including construction, infrastructure development, and mechanization services across Ghana.</p>
                </div>
            </div>
        </section>

        <!-- Why Choose Us -->
        <section style="margin-bottom: 4rem; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); padding: 4rem 2rem; border-radius: 16px;">
            <h2 style="font-size: 2.5rem; color: #1e293b; margin-bottom: 3rem; text-align: center; font-weight: 700;">Why Choose Kari Boreholes?</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                <div style="text-align: center;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem;">✅</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">100% Guaranteed</h3>
                    <p style="color: #64748b; line-height: 1.6;">Quality workmanship and reliable service you can trust. We stand behind every project with comprehensive guarantees.</p>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem;">🏆</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Expert Team</h3>
                    <p style="color: #64748b; line-height: 1.6;">Years of experience and professional expertise. Our team includes certified engineers, geologists, and drilling specialists.</p>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem;">⚡</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Fast Service</h3>
                    <p style="color: #64748b; line-height: 1.6;">Quick turnaround without compromising quality. We understand the urgency of water access and work efficiently to deliver results.</p>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem;">💰</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Fair Pricing</h3>
                    <p style="color: #64748b; line-height: 1.6;">Competitive rates with transparent pricing. No hidden fees, no surprises—just honest, fair pricing for quality work.</p>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem;">🔬</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Advanced Technology</h3>
                    <p style="color: #64748b; line-height: 1.6;">Our proprietary ABBIS system ensures precision, efficiency, and data-driven decision making for every project.</p>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem;">🌍</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Nationwide Service</h3>
                    <p style="color: #64748b; line-height: 1.6;">Serving communities across Ghana. From Accra to Tamale, we bring water solutions wherever they\'re needed.</p>
                </div>
            </div>
        </section>

        <!-- Our Technology -->
        <section style="margin-bottom: 4rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center;">
                <div style="background: #0ea5e9; color: white; padding: 3rem; border-radius: 16px; box-shadow: 0 8px 24px rgba(14, 165, 233, 0.3);">
                    <h2 style="font-size: 2.5rem; margin-bottom: 1.5rem; font-weight: 700;">ABBIS Technology</h2>
                    <p style="font-size: 1.15rem; line-height: 1.8; margin-bottom: 1.5rem; opacity: 0.95;">
                        We leverage our proprietary <strong>Advanced Borehole Business Intelligence System (ABBIS)</strong> to ensure precision, efficiency, and excellence in every project. This cutting-edge system provides:
                    </p>
                    <ul style="font-size: 1.1rem; line-height: 2; list-style: none; padding: 0;">
                        <li style="margin-bottom: 0.75rem;">✓ Real-time project tracking and analytics</li>
                        <li style="margin-bottom: 0.75rem;">✓ Data-driven drilling decisions</li>
                        <li style="margin-bottom: 0.75rem;">✓ Comprehensive maintenance scheduling</li>
                        <li style="margin-bottom: 0.75rem;">✓ Financial transparency and reporting</li>
                        <li style="margin-bottom: 0.75rem;">✓ Client relationship management</li>
                    </ul>
                </div>
                <div>
                    <h3 style="font-size: 2rem; color: #1e293b; margin-bottom: 1.5rem; font-weight: 600;">Our Commitment</h3>
                    <p style="font-size: 1.15rem; line-height: 1.8; color: #475569; margin-bottom: 1.5rem;">
                        At Kari Boreholes, we don\'t just drill wells—we create sustainable water solutions that transform communities. Every project is backed by our commitment to:
                    </p>
                    <div style="background: #f8fafc; padding: 2rem; border-radius: 12px; border-left: 5px solid #0ea5e9;">
                        <ul style="font-size: 1.1rem; line-height: 2; color: #475569; list-style: none; padding: 0;">
                            <li style="margin-bottom: 0.75rem;">🎯 <strong>Quality:</strong> We never compromise on materials or workmanship</li>
                            <li style="margin-bottom: 0.75rem;">🤝 <strong>Integrity:</strong> Honest communication and transparent processes</li>
                            <li style="margin-bottom: 0.75rem;">🌱 <strong>Sustainability:</strong> Solutions that last for generations</li>
                            <li style="margin-bottom: 0.75rem;">👥 <strong>Community:</strong> Empowering communities through water access</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section style="margin-bottom: 4rem; background: #1e293b; color: white; padding: 4rem 2rem; border-radius: 16px;">
            <h2 style="font-size: 2.5rem; margin-bottom: 3rem; text-align: center; font-weight: 700;">Our Impact</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; text-align: center;">
                <div>
                    <div style="font-size: 3.5rem; font-weight: 700; color: #0ea5e9; margin-bottom: 0.5rem;">500+</div>
                    <div style="font-size: 1.1rem; opacity: 0.9;">Projects Completed</div>
                </div>
                <div>
                    <div style="font-size: 3.5rem; font-weight: 700; color: #10b981; margin-bottom: 0.5rem;">100+</div>
                    <div style="font-size: 1.1rem; opacity: 0.9;">Communities Served</div>
                </div>
                <div>
                    <div style="font-size: 3.5rem; font-weight: 700; color: #f59e0b; margin-bottom: 0.5rem;">15+</div>
                    <div style="font-size: 1.1rem; opacity: 0.9;">Years of Experience</div>
                </div>
                <div>
                    <div style="font-size: 3.5rem; font-weight: 700; color: #8b5cf6; margin-bottom: 0.5rem;">98%</div>
                    <div style="font-size: 1.1rem; opacity: 0.9;">Client Satisfaction</div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section style="text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
            <h2 style="font-size: 2.5rem; margin-bottom: 1.5rem; font-weight: 700;">Ready to Get Started?</h2>
            <p style="font-size: 1.3rem; margin-bottom: 2.5rem; opacity: 0.95; max-width: 700px; margin-left: auto; margin-right: auto; line-height: 1.6;">
                Let\'s work together to bring clean, reliable water to your community or project. Contact us today for a free consultation and quote.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="/cms/contact" style="padding: 1rem 2.5rem; background: white; color: #0ea5e9; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 1.1rem; transition: transform 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.2);" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">Contact Us</a>
                <a href="/cms/quote" style="padding: 1rem 2.5rem; background: transparent; color: white; text-decoration: none; border: 2px solid white; border-radius: 8px; font-weight: 700; font-size: 1.1rem; transition: all 0.2s;" onmouseover="this.style.background='white'; this.style.color='#0ea5e9'" onmouseout="this.style.background='transparent'; this.style.color='white'">Get a Quote</a>
            </div>
        </section>
    </div>',
    'published',
    'About Us - Kari Boreholes | Professional Water Solutions in Ghana',
    'Learn about Kari Boreholes and Civil Engineering Works. We provide professional borehole drilling, water systems, and engineering services across Ghana with over 15 years of experience.',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    content = VALUES(content),
    seo_title = VALUES(seo_title),
    seo_description = VALUES(seo_description),
    updated_at = NOW();

