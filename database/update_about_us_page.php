<?php
/**
 * Update About Us page - World-class sleek design with proper width constraints
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();

$content = '<div class="about-us-content" style="width: 100%; max-width: 100vw; padding: 4rem 6rem; background: #f8fafc; box-sizing: border-box; overflow-x: hidden;">
        <div style="max-width: 1400px; margin: 0 auto; width: 100%; box-sizing: border-box;">
        <!-- Hero Section -->
        <div style="text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; border-radius: 20px; margin-bottom: 4rem; box-shadow: 0 12px 40px rgba(14, 165, 233, 0.25);">
            <h1 style="font-size: 3.2rem; font-weight: 700; margin-bottom: 1.2rem; text-shadow: 2px 2px 8px rgba(0,0,0,0.2); letter-spacing: -0.5px; color: #ffffff;">About Kari Boreholes</h1>
            <p style="font-size: 1.4rem; color: #ffffff; max-width: 700px; margin: 0 auto; line-height: 1.6; font-weight: 400;">Delivering Excellence in Water Solutions Across Ghana</p>
        </div>

        <!-- Mission & Vision - Side by Side -->
        <section style="margin-bottom: 4rem;">
            <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 3rem; align-items: start;">
                <div>
                    <h2 style="font-size: 2.2rem; color: #1e293b; margin-bottom: 1.5rem; font-weight: 700; letter-spacing: -0.3px;">Our Mission</h2>
                    <p style="font-size: 1.05rem; line-height: 1.8; color: #475569; margin-bottom: 1.2rem; font-weight: 400;">
                        At Kari Boreholes and Civil Engineering Works, we are committed to providing reliable, sustainable water solutions that transform communities across Ghana. Our mission is to ensure every community has access to clean, safe water through professional borehole drilling, advanced water systems, and comprehensive engineering services.
                    </p>
                    <p style="font-size: 1.05rem; line-height: 1.8; color: #475569; font-weight: 400;">
                        We combine decades of experience with cutting-edge technology, including our proprietary ABBIS (Advanced Borehole Business Intelligence System), to deliver projects that exceed expectations and stand the test of time.
                    </p>
                </div>
                <div style="background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); padding: 3rem; border-radius: 20px; border-left: 5px solid #0ea5e9; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <div style="font-size: 4rem; margin-bottom: 1.2rem; text-align: center;">💧</div>
                    <h3 style="font-size: 1.4rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600; text-align: center;">Water for Life</h3>
                    <p style="color: #64748b; line-height: 1.7; text-align: center; font-size: 1rem; font-weight: 400;">Every project we complete brings us closer to our vision: a Ghana where clean water is accessible to all.</p>
                </div>
            </div>
        </section>

        <!-- What We Do - 3 Columns -->
        <section style="margin-bottom: 4rem;">
            <h2 style="font-size: 2.2rem; color: #1e293b; margin-bottom: 2.5rem; text-align: center; font-weight: 700; letter-spacing: -0.3px;">What We Do</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem;">
                <div style="background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 4px solid #0ea5e9; transition: all 0.3s ease; height: 100%;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🕳️</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.875rem; font-weight: 600;">Borehole Drilling</h3>
                    <p style="color: #64748b; line-height: 1.7; font-size: 0.95rem; font-weight: 400;">Professional borehole drilling services using state-of-the-art equipment. We handle everything from site assessment to completion.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 4px solid #10b981; transition: all 0.3s ease; height: 100%;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.875rem; font-weight: 600;">Geophysical Survey</h3>
                    <p style="color: #64748b; line-height: 1.7; font-size: 0.95rem; font-weight: 400;">Advanced geophysical surveys to identify optimal drilling locations using cutting-edge technology.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 4px solid #f59e0b; transition: all 0.3s ease; height: 100%;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚙️</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.875rem; font-weight: 600;">Pump Installation</h3>
                    <p style="color: #64748b; line-height: 1.7; font-size: 0.95rem; font-weight: 400;">Complete pump installation and automation systems. From submersible pumps to smart control systems.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 4px solid #8b5cf6; transition: all 0.3s ease; height: 100%;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">💧</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.875rem; font-weight: 600;">Water Treatment</h3>
                    <p style="color: #64748b; line-height: 1.7; font-size: 0.95rem; font-weight: 400;">Comprehensive water treatment solutions including filtration, reverse osmosis, and UV purification.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 4px solid #ef4444; transition: all 0.3s ease; height: 100%;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔧</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.875rem; font-weight: 600;">Maintenance & Repair</h3>
                    <p style="color: #64748b; line-height: 1.7; font-size: 0.95rem; font-weight: 400;">Regular maintenance, rehabilitation, and repair services to keep your water systems running efficiently.</p>
                </div>
                
                <div style="background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 4px solid #06b6d4; transition: all 0.3s ease; height: 100%;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🏗️</div>
                    <h3 style="font-size: 1.3rem; color: #1e293b; margin-bottom: 0.875rem; font-weight: 600;">Civil Engineering</h3>
                    <p style="color: #64748b; line-height: 1.7; font-size: 0.95rem; font-weight: 400;">Full-service civil engineering works including construction, infrastructure development, and mechanization services.</p>
                </div>
            </div>
        </section>

        <!-- Why Choose Us & Stats - Side by Side -->
        <section style="margin-bottom: 4rem;">
            <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 3rem;">
                <!-- Why Choose Us -->
                <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); padding: 3rem; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.1);">
                    <h2 style="font-size: 2.2rem; color: #1e293b; margin-bottom: 2rem; font-weight: 700; letter-spacing: -0.3px;">Why Choose Kari Boreholes?</h2>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem;">
                        <div style="padding: 2rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                            <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">100% Guaranteed</h3>
                            <p style="color: #64748b; line-height: 1.6; font-size: 0.95rem; font-weight: 400;">Quality workmanship and reliable service you can trust.</p>
                        </div>
                        
                        <div style="padding: 2rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🏆</div>
                            <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Expert Team</h3>
                            <p style="color: #64748b; line-height: 1.6; font-size: 0.95rem; font-weight: 400;">Years of experience and professional expertise.</p>
                        </div>
                        
                        <div style="padding: 2rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">⚡</div>
                            <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Fast Service</h3>
                            <p style="color: #64748b; line-height: 1.6; font-size: 0.95rem; font-weight: 400;">Quick turnaround without compromising quality.</p>
                        </div>
                        
                        <div style="padding: 2rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">💰</div>
                            <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Fair Pricing</h3>
                            <p style="color: #64748b; line-height: 1.6; font-size: 0.95rem; font-weight: 400;">Competitive rates with transparent pricing.</p>
                        </div>
                        
                        <div style="padding: 2rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🔬</div>
                            <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Advanced Technology</h3>
                            <p style="color: #64748b; line-height: 1.6; font-size: 0.95rem; font-weight: 400;">Our proprietary ABBIS system ensures precision.</p>
                        </div>
                        
                        <div style="padding: 2rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🌍</div>
                            <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 0.75rem; font-weight: 600;">Nationwide Service</h3>
                            <p style="color: #64748b; line-height: 1.6; font-size: 0.95rem; font-weight: 400;">Serving communities across Ghana.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Stats -->
                <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; padding: 3rem; border-radius: 20px; box-shadow: 0 12px 40px rgba(30, 41, 59, 0.4);">
                    <h2 style="font-size: 2.2rem; margin-bottom: 2.5rem; text-align: center; font-weight: 700; letter-spacing: -0.3px; color: #ffffff;">Our Impact</h2>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; text-align: center;">
                        <div style="padding: 1.5rem; background: rgba(255,255,255,0.15); border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
                            <div style="font-size: 3rem; font-weight: 700; color: #38bdf8; margin-bottom: 0.75rem;">500+</div>
                            <div style="font-size: 0.95rem; color: #ffffff; font-weight: 500;">Projects</div>
                        </div>
                        <div style="padding: 1.5rem; background: rgba(255,255,255,0.15); border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
                            <div style="font-size: 3rem; font-weight: 700; color: #22c55e; margin-bottom: 0.75rem;">100+</div>
                            <div style="font-size: 0.95rem; color: #ffffff; font-weight: 500;">Communities</div>
                        </div>
                        <div style="padding: 1.5rem; background: rgba(255,255,255,0.15); border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
                            <div style="font-size: 3rem; font-weight: 700; color: #f59e0b; margin-bottom: 0.75rem;">15+</div>
                            <div style="font-size: 0.95rem; color: #ffffff; font-weight: 500;">Years Experience</div>
                        </div>
                        <div style="padding: 1.5rem; background: rgba(255,255,255,0.15); border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
                            <div style="font-size: 3rem; font-weight: 700; color: #a855f7; margin-bottom: 0.75rem;">98%</div>
                            <div style="font-size: 0.95rem; color: #ffffff; font-weight: 500;">Satisfaction</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Our Impact Section - Full Stats -->
        <section style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); padding: 4rem 2rem; margin: 4rem 0; border-radius: 20px; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #2563eb, #10b981, #f59e0b, #ef4444);"></div>
            <div style="max-width: 1200px; margin: 0 auto;">
                <div style="text-align: center; margin-bottom: 3rem;">
                    <h2 style="font-size: 2.5rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">Our Impact</h2>
                    <p style="font-size: 1.125rem; color: #64748b; max-width: 600px; margin: 0 auto;">Delivering excellence in water solutions across Ghana</p>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem;">
                    <div style="background: white; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; border-top: 4px solid #2563eb;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">📊</div>
                        <div style="font-size: 3rem; font-weight: 700; color: #2563eb; margin-bottom: 0.5rem;">500+</div>
                        <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Projects Completed</div>
                    </div>
                    <div style="background: white; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; border-top: 4px solid #10b981;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">👥</div>
                        <div style="font-size: 3rem; font-weight: 700; color: #10b981; margin-bottom: 0.5rem;">100+</div>
                        <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Communities Served</div>
                    </div>
                    <div style="background: white; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; border-top: 4px solid #f59e0b;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">⏱️</div>
                        <div style="font-size: 3rem; font-weight: 700; color: #f59e0b; margin-bottom: 0.5rem;">15+</div>
                        <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Years Experience</div>
                    </div>
                    <div style="background: white; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; border-top: 4px solid #ef4444;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">⭐</div>
                        <div style="font-size: 3rem; font-weight: 700; color: #ef4444; margin-bottom: 0.5rem;">98%</div>
                        <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Client Satisfaction</div>
                    </div>
                    <div style="background: white; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; border-top: 4px solid #8b5cf6;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">💧</div>
                        <div style="font-size: 3rem; font-weight: 700; color: #8b5cf6; margin-bottom: 0.5rem;">1,200+</div>
                        <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Water Wells Drilled</div>
                    </div>
                    <div style="background: white; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; border-top: 4px solid #06b6d4;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">🌍</div>
                        <div style="font-size: 3rem; font-weight: 700; color: #06b6d4; margin-bottom: 0.5rem;">500K+</div>
                        <div style="font-size: 1rem; color: #64748b; font-weight: 500;">People Served</div>
                    </div>
                    <div style="background: white; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; border-top: 4px solid #14b8a6;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">🚛</div>
                        <div style="font-size: 3rem; font-weight: 700; color: #14b8a6; margin-bottom: 0.5rem;">10+</div>
                        <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Active Rigs</div>
                    </div>
                    <div style="background: white; border-radius: 16px; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; border-top: 4px solid #f97316;">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">👷</div>
                        <div style="font-size: 3rem; font-weight: 700; color: #f97316; margin-bottom: 0.5rem;">50+</div>
                        <div style="font-size: 1rem; color: #64748b; font-weight: 500;">Expert Engineers</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ABBIS Technology & Commitment - Side by Side -->
        <section style="margin-bottom: 4rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem;">
                <div style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 3rem; border-radius: 20px; box-shadow: 0 12px 40px rgba(14, 165, 233, 0.3);">
                    <h2 style="font-size: 2.2rem; margin-bottom: 1.5rem; font-weight: 700; letter-spacing: -0.3px; color: #ffffff;">ABBIS Technology</h2>
                    <p style="font-size: 1.05rem; line-height: 1.8; margin-bottom: 2rem; color: #ffffff; font-weight: 400;">
                        We leverage our proprietary <strong style="color: #ffffff; font-weight: 600;">Advanced Borehole Business Intelligence System (ABBIS)</strong> to ensure precision, efficiency, and excellence in every project.
                    </p>
                    <ul style="font-size: 1rem; line-height: 2; list-style: none; padding: 0; font-weight: 400;">
                        <li style="margin-bottom: 0.75rem; padding-left: 1.5rem; position: relative; color: #ffffff;">
                            <span style="position: absolute; left: 0; color: #ffffff;">✓</span> Real-time project tracking
                        </li>
                        <li style="margin-bottom: 0.75rem; padding-left: 1.5rem; position: relative; color: #ffffff;">
                            <span style="position: absolute; left: 0; color: #ffffff;">✓</span> Data-driven decisions
                        </li>
                        <li style="margin-bottom: 0.75rem; padding-left: 1.5rem; position: relative; color: #ffffff;">
                            <span style="position: absolute; left: 0; color: #ffffff;">✓</span> Maintenance scheduling
                        </li>
                        <li style="margin-bottom: 0.75rem; padding-left: 1.5rem; position: relative; color: #ffffff;">
                            <span style="position: absolute; left: 0; color: #ffffff;">✓</span> Financial transparency
                        </li>
                        <li style="margin-bottom: 0.75rem; padding-left: 1.5rem; position: relative; color: #ffffff;">
                            <span style="position: absolute; left: 0; color: #ffffff;">✓</span> Client management
                        </li>
                    </ul>
                </div>
                <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); padding: 3rem; border-radius: 20px; border-left: 5px solid #0ea5e9; box-shadow: 0 8px 30px rgba(0,0,0,0.1);">
                    <h3 style="font-size: 2rem; color: #1e293b; margin-bottom: 1.5rem; font-weight: 600; letter-spacing: -0.3px;">Our Commitment</h3>
                    <p style="font-size: 1.05rem; line-height: 1.8; color: #475569; margin-bottom: 2rem; font-weight: 400;">
                        At Kari Boreholes, we don\'t just drill wells—we create sustainable water solutions that transform communities.
                    </p>
                    <ul style="font-size: 1rem; line-height: 2; color: #475569; list-style: none; padding: 0; font-weight: 400;">
                        <li style="margin-bottom: 1rem; padding-left: 2rem; position: relative;">
                            <span style="position: absolute; left: 0; font-size: 1.2rem;">🎯</span> <strong>Quality:</strong> Never compromise on materials or workmanship
                        </li>
                        <li style="margin-bottom: 1rem; padding-left: 2rem; position: relative;">
                            <span style="position: absolute; left: 0; font-size: 1.2rem;">🤝</span> <strong>Integrity:</strong> Honest communication and transparent processes
                        </li>
                        <li style="margin-bottom: 1rem; padding-left: 2rem; position: relative;">
                            <span style="position: absolute; left: 0; font-size: 1.2rem;">🌱</span> <strong>Sustainability:</strong> Solutions that last for generations
                        </li>
                        <li style="margin-bottom: 1rem; padding-left: 2rem; position: relative;">
                            <span style="position: absolute; left: 0; font-size: 1.2rem;">👥</span> <strong>Community:</strong> Empowering communities through water access
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section style="text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; border-radius: 20px; box-shadow: 0 12px 40px rgba(14, 165, 233, 0.25);">
            <h2 style="font-size: 2.5rem; margin-bottom: 1.2rem; font-weight: 700; letter-spacing: -0.5px; color: #ffffff;">Ready to Get Started?</h2>
            <p style="font-size: 1.2rem; margin-bottom: 2.5rem; color: #ffffff; max-width: 650px; margin-left: auto; margin-right: auto; line-height: 1.7; font-weight: 400;">
                Let\'s work together to bring clean, reliable water to your community or project. Contact us today for a free consultation and quote.
            </p>
            <div style="display: flex; gap: 1.25rem; justify-content: center; flex-wrap: wrap;">
                <a href="/abbis3.2/cms/contact" style="padding: 1.1rem 2.5rem; background: #ffffff; color: #0ea5e9; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 1.05rem; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(0,0,0,0.2); display: inline-block;">Contact Us</a>
                <a href="/abbis3.2/cms/quote" style="padding: 1.1rem 2.5rem; background: rgba(255,255,255,0.2); color: #ffffff; text-decoration: none; border: 2px solid #ffffff; border-radius: 10px; font-weight: 700; font-size: 1.05rem; transition: all 0.3s ease; display: inline-block; backdrop-filter: blur(10px);">Get a Quote</a>
            </div>
        </section>
        </div>
    </div>

    <style>
        .about-us-content {
            box-sizing: border-box;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }
        .about-us-content > div {
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
        }
        .about-us-content * {
            box-sizing: border-box;
            max-width: 100%;
        }
        .about-us-content section > div[style*="grid"] {
            width: 100%;
            max-width: 100%;
        }
        @media (max-width: 1600px) {
            .about-us-content {
                padding: 4rem 5rem !important;
            }
        }
        @media (max-width: 1400px) {
            .about-us-content {
                padding: 3rem 4rem !important;
            }
        }
        @media (max-width: 1200px) {
            .about-us-content {
                padding: 3rem 3rem !important;
            }
            .about-us-content section > div[style*="grid-template-columns: repeat(3"] {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .about-us-content section > div[style*="grid-template-columns: 1.4fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
            .about-us-content section > div[style*="grid-template-columns: repeat(4"] {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media (max-width: 968px) {
            .about-us-content {
                padding: 2.5rem 2rem !important;
            }
            .about-us-content section > div[style*="grid"] {
                grid-template-columns: 1fr !important;
            }
        }
        @media (max-width: 768px) {
            .about-us-content {
                padding: 2rem 1.5rem !important;
            }
        }
    </style>';

try {
    // Check if page exists
    $checkStmt = $pdo->prepare("SELECT id FROM cms_pages WHERE slug = ? LIMIT 1");
    $checkStmt->execute(['about-us']);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing page
        $stmt = $pdo->prepare("UPDATE cms_pages SET 
            title = ?,
            content = ?,
            seo_title = ?,
            seo_description = ?,
            updated_at = NOW()
            WHERE slug = ?");
        $stmt->execute([
            'About Us',
            $content,
            'About Us - Kari Boreholes | Professional Water Solutions in Ghana',
            'Learn about Kari Boreholes and Civil Engineering Works. We provide professional borehole drilling, water systems, and engineering services across Ghana with over 15 years of experience.',
            'about-us'
        ]);
        echo "✅ About Us page updated with world-class design!\n";
    } else {
        // Create new page
        $stmt = $pdo->prepare("INSERT INTO cms_pages (title, slug, content, status, seo_title, seo_description, created_at, updated_at) 
            VALUES (?, ?, ?, 'published', ?, ?, NOW(), NOW())");
        $stmt->execute([
            'About Us',
            'about-us',
            $content,
            'About Us - Kari Boreholes | Professional Water Solutions in Ghana',
            'Learn about Kari Boreholes and Civil Engineering Works. We provide professional borehole drilling, water systems, and engineering services across Ghana with over 15 years of experience.'
        ]);
        echo "✅ About Us page created with world-class design!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
