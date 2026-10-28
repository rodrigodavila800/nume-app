// JavaScript Document

/*

TemplateMo 600 Prism Flux

https://templatemo.com/tm-600-prism-flux

*/


        const rawPortfolioData = (window.numePortfolioData && window.numePortfolioData.length > 0)
            ? window.numePortfolioData
            : [
            {
                id: 1,
                title: 'Neural Network',
                description: 'Advanced AI system with deep learning capabilities for predictive analytics and pattern recognition.',
                image: 'images/neural-network.jpg',
                tech: ['TensorFlow', 'Python', 'CUDA'],
                is_vertical: true
            },
            {
                id: 2,
                title: 'Quantum Cloud',
                description: 'Next-generation cloud infrastructure leveraging quantum computing for unprecedented processing power.',
                image: 'images/quantum-cloud.jpg',
                tech: ['AWS', 'Kubernetes', 'Docker'],
                is_vertical: true
            },
            {
                id: 3,
                title: 'Blockchain Vault',
                description: 'Secure decentralized storage solution using advanced encryption and distributed ledger technology.',
                image: 'images/blockchain-vault.jpg',
                tech: ['Ethereum', 'Solidity', 'Web3'],
                is_vertical: false
            }
        ];

        let portfolioData = [...rawPortfolioData];

        // Skills data
        const skillsData = (window.numeSkillsData && window.numeSkillsData.length > 0)
            ? window.numeSkillsData
            : [
            { name: 'React.js', icon: '01', level: 95, category: 'frontend' },
            { name: 'Node.js', icon: '02', level: 90, category: 'backend' },
            { name: 'TypeScript', icon: '03', level: 88, category: 'frontend' },
            { name: 'AWS', icon: '04', level: 92, category: 'cloud' },
            { name: 'Docker', icon: '05', level: 85, category: 'cloud' },
            { name: 'Python', icon: '06', level: 93, category: 'backend' },
            { name: 'Kubernetes', icon: '07', level: 82, category: 'cloud' },
            { name: 'GraphQL', icon: '◈', level: 87, category: 'backend' },
            { name: 'TensorFlow', icon: '08', level: 78, category: 'emerging' },
            { name: 'Blockchain', icon: '09', level: 75, category: 'emerging' },
            { name: 'Vue.js', icon: '10', level: 85, category: 'frontend' },
            { name: 'MongoDB', icon: '11', level: 90, category: 'backend' }
        ];

        // Scroll to section function
        function scrollToSection(sectionId) {
            const section = document.getElementById(sectionId);
            const header = document.getElementById('header');
            if (section) {
                const headerHeight = header.offsetHeight;
                const targetPosition = section.offsetTop - headerHeight;
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        }

        // Initialize particles for philosophy section
        function initParticles() {
            const particlesContainer = document.getElementById('particles');
            if (!particlesContainer) return;
            const particleCount = 15;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random horizontal position
                particle.style.left = Math.random() * 100 + '%';
                
                // Start particles at random vertical positions throughout the section
                particle.style.top = Math.random() * 100 + '%';
                
                // Random animation delay for natural movement
                particle.style.animationDelay = Math.random() * 20 + 's';
                
                // Random animation duration for variety
                particle.style.animationDuration = (18 + Math.random() * 8) + 's';
                
                particlesContainer.appendChild(particle);
            }
        }

        // =========================================================================
        // ORBITAL 3D RING CAROUSEL ENGINE (Template 2167 Orbital Integration)
        // =========================================================================
        const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const spacingLevels = [0.76, 0.95, 1.12];
        let spacingIndex = 1;
        let orbitalRotation = 0;
        let orbitalVelocity = 0;
        let orbitalDragging = false;
        let orbitalLastX = 0;
        let orbitalDragDistance = 0;
        let orbitalLoopStarted = false;

        function baseOrbitalRadius() {
            const raw = getComputedStyle(document.documentElement).getPropertyValue('--orbital-radius');
            return parseFloat(raw) || 360;
        }

        function effectiveOrbitalRadius() {
            return baseOrbitalRadius() * spacingLevels[spacingIndex];
        }

        function positionOrbitalPanels() {
            const ring = document.getElementById('carousel');
            if (!ring) return;
            const panels = ring.querySelectorAll('.orbital-panel');
            const count = panels.length;
            if (!count) return;

            const r = effectiveOrbitalRadius();
            panels.forEach((panel, i) => {
                const angle = (360 / count) * i;
                const tilt = Math.sin((i / count) * Math.PI * 2) * 7.5;
                panel.style.setProperty('--ry', angle + 'deg');
                panel.style.setProperty('--tz', r + 'px');
                panel.style.setProperty('--rz', tilt.toFixed(2) + 'deg');
                panel.style.setProperty('--i', i);
            });
        }

        function createCarouselItem(data, index) {
            const panel = document.createElement('div');
            panel.className = 'orbital-panel';
            panel.dataset.index = index;

            const isVertical = data.is_vertical !== false;
            const videoUrl = data.video_url || '#';
            const embedUrl = data.embed_url || '';
            const numStr = String(index + 1).padStart(2, '0');
            const formatBadge = isVertical ? '9:16 SHORTS' : '16:9 WIDE';
            const categoryName = (data.tech && data.tech[1]) || 'NUME 4K';

            panel.innerHTML = `
                <div class="orbital-panel-face orbital-panel-face--text">
                    <span class="p-num">${numStr} · ${categoryName}</span>
                    <span class="p-label">${data.title || ''}</span>
                    <span class="p-meta">${formatBadge}</span>
                </div>
                <div class="orbital-panel-face orbital-panel-face--image">
                    <img src="${data.image}" alt="${(data.title || '').replace(/"/g, '&quot;')}" loading="lazy">
                    <div class="orbital-panel-scrim"></div>
                    <span class="orbital-panel-badge">${formatBadge}</span>
                    <div class="orbital-panel-play">▶</div>
                    <div class="orbital-panel-cap">
                        <span class="p-num">${numStr}</span>
                        <span class="orbital-panel-cap-title">${data.title || ''}</span>
                    </div>
                </div>
            `;

            panel.addEventListener('click', (e) => {
                if (orbitalDragDistance > 8) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                if (window.openVideoModal) {
                    window.openVideoModal(videoUrl, data.title, isVertical, embedUrl);
                }
            });

            return panel;
        }

        function rebuildCarousel() {
            const carousel = document.getElementById('carousel');
            if (!carousel) return;

            carousel.innerHTML = '';

            if (!portfolioData || portfolioData.length === 0) {
                carousel.style.transform = 'none';
                carousel.innerHTML = `
                    <div class="orbital-empty-state">
                        ${(window.__ ? window.__('no_videos_found', 'Nenhum vídeo encontrado para este formato no momento.') : 'Nenhum vídeo encontrado para este formato no momento.')}
                    </div>
                `;
                return;
            }

            // Ensure at least 8 panels around the 360° cylinder so the 3D ring geometry looks complete
            const ringItems = [];
            const minPanels = 8;
            const targetCount = Math.max(portfolioData.length, minPanels);
            for (let i = 0; i < targetCount; i++) {
                ringItems.push(portfolioData[i % portfolioData.length]);
            }

            ringItems.forEach((data, index) => {
                const item = createCarouselItem(data, index);
                carousel.appendChild(item);
            });

            positionOrbitalPanels();
        }

        window.filterPortfolio = function(filterType, btnEl) {
            const buttons = document.querySelectorAll('.portfolio-tab-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });

            if (btnEl) {
                btnEl.classList.add('active');
            }

            if (filterType === 'vertical') {
                portfolioData = rawPortfolioData.filter(item => item.is_vertical !== false);
            } else if (filterType === 'horizontal') {
                portfolioData = rawPortfolioData.filter(item => item.is_vertical === false);
            } else {
                portfolioData = [...rawPortfolioData];
            }

            rebuildCarousel();
        };

        function isModalOpen() {
            const modal = document.getElementById('storefront-video-modal');
            return modal && (modal.classList.contains('is-open') || modal.style.display === 'flex');
        }

        function initCarousel() {
            rebuildCarousel();

            if (orbitalLoopStarted) return;
            orbitalLoopStarted = true;

            const wrapper = document.getElementById('orbitalWrapper');
            const stage = document.getElementById('orbitalStage');
            const parallax = document.getElementById('orbitalParallax');
            const ringTilt = document.getElementById('orbitalRingTilt');
            const ring = document.getElementById('carousel');

            const baseDrift = reduceMotion ? 0 : 0.12;
            const friction = 0.94;
            const MAX_VELOCITY = 7;
            const DRAG_SENS = 0.32;
            const WHEEL_SENS = 0.05;

            function clamp(v, min, max) {
                return Math.max(min, Math.min(max, v));
            }

            let targetX = 8, targetY = 0, currentX = 8, currentY = 0;
            const rangeY = 24;
            const rangeX = 24;
            const biasX = 8;

            if (!reduceMotion) {
                window.addEventListener('mousemove', (e) => {
                    const mx = (e.clientX / window.innerWidth) - 0.5;
                    const my = (e.clientY / window.innerHeight) - 0.5;
                    targetY = mx * rangeY;
                    targetX = (-my * rangeX) + biasX;
                });
            }

            if (stage) {
                stage.addEventListener('pointerdown', (e) => {
                    orbitalDragging = true;
                    orbitalLastX = e.clientX;
                    orbitalDragDistance = 0;
                    orbitalVelocity = 0;
                    stage.classList.add('dragging');
                });

                window.addEventListener('pointermove', (e) => {
                    if (!orbitalDragging) return;
                    const dx = e.clientX - orbitalLastX;
                    orbitalLastX = e.clientX;
                    orbitalDragDistance += Math.abs(dx);
                    const step = dx * DRAG_SENS;
                    orbitalRotation += step;
                    orbitalVelocity = clamp(step, -MAX_VELOCITY, MAX_VELOCITY);
                });

                const endDrag = () => {
                    if (!orbitalDragging) return;
                    orbitalDragging = false;
                    stage.classList.remove('dragging');
                };

                window.addEventListener('pointerup', endDrag);
                window.addEventListener('pointercancel', endDrag);

                stage.addEventListener('wheel', (e) => {
                    if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
                        e.preventDefault();
                        orbitalVelocity = clamp(orbitalVelocity + e.deltaX * WHEEL_SENS, -MAX_VELOCITY, MAX_VELOCITY);
                    }
                }, { passive: false });
            }

            // Keyboard left/right spins the orbital ring
            document.addEventListener('keydown', (e) => {
                if (isModalOpen()) return;
                if (e.key === 'ArrowLeft') orbitalVelocity = clamp(orbitalVelocity + 2.2, -MAX_VELOCITY, MAX_VELOCITY);
                if (e.key === 'ArrowRight') orbitalVelocity = clamp(orbitalVelocity - 2.2, -MAX_VELOCITY, MAX_VELOCITY);
            });

            // HUD Controls: VISUALS, ZOOM, SPACING
            const visualsSwitch = document.getElementById('visualsSwitch');
            if (visualsSwitch && wrapper) {
                visualsSwitch.addEventListener('click', () => {
                    const on = visualsSwitch.getAttribute('aria-checked') !== 'true';
                    visualsSwitch.setAttribute('aria-checked', on ? 'true' : 'false');
                    wrapper.classList.toggle('visuals-on', on);
                });
            }

            const zoomSwitch = document.getElementById('zoomSwitch');
            if (zoomSwitch && ringTilt) {
                zoomSwitch.addEventListener('click', () => {
                    const on = zoomSwitch.getAttribute('aria-checked') !== 'true';
                    zoomSwitch.setAttribute('aria-checked', on ? 'true' : 'false');
                    ringTilt.style.setProperty('--zoom', on ? '1.22' : '1');
                });
            }

            const spacingSteps = document.querySelectorAll('.orbital-spacing-step');
            spacingSteps.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.getAttribute('data-space'), 10);
                    spacingIndex = isNaN(idx) ? 1 : idx;
                    const r = effectiveOrbitalRadius();
                    if (ring) {
                        ring.querySelectorAll('.orbital-panel').forEach((p) => {
                            p.style.setProperty('--tz', r + 'px');
                        });
                    }
                    spacingSteps.forEach((b) => {
                        b.classList.toggle('is-active', parseInt(b.getAttribute('data-space'), 10) === spacingIndex);
                    });
                });
            });

            function orbitalFrame() {
                if (!orbitalDragging && !isModalOpen()) {
                    orbitalRotation += baseDrift + orbitalVelocity;
                    orbitalVelocity *= friction;
                    if (Math.abs(orbitalVelocity) < 0.0015) orbitalVelocity = 0;
                }
                if (ring && portfolioData && portfolioData.length > 0) {
                    ring.style.transform = 'rotateY(' + orbitalRotation.toFixed(3) + 'deg)';
                }
                if (!reduceMotion && parallax) {
                    currentX += (targetX - currentX) * 0.06;
                    currentY += (targetY - currentY) * 0.06;
                    parallax.style.transform = 'rotateX(' + currentX.toFixed(2) + 'deg) rotateY(' + currentY.toFixed(2) + 'deg)';
                }
                requestAnimationFrame(orbitalFrame);
            }

            orbitalFrame();
        }

        function updateCarousel() {
            positionOrbitalPanels();
        }

        // Initialize hexagonal skills grid
        function initSkillsGrid() {
            const skillsGrid = document.getElementById('skillsGrid');
            if (!skillsGrid) return;
            const categoryTabs = document.querySelectorAll('.category-tab');
            
            function displaySkills(category = 'all') {
                skillsGrid.innerHTML = '';
                
                const filteredSkills = category === 'all' 
                    ? skillsData 
                    : skillsData.filter(skill => skill.category === category);
                
                filteredSkills.forEach((skill, index) => {
                    const hexagon = document.createElement('div');
                    hexagon.className = 'skill-hexagon';
                    hexagon.style.animationDelay = `${index * 0.1}s`;
                    
                    hexagon.innerHTML = `
                        <div class="hexagon-inner">
                            <div class="hexagon-content">
                                <div class="skill-icon-hex">${skill.icon}</div>
                                <div class="skill-name-hex">${skill.name}</div>
                                <div class="skill-level">
                                    <div class="skill-level-fill" style="width: ${skill.level}%"></div>
                                </div>
                                <div class="skill-percentage-hex">${skill.level}%</div>
                            </div>
                        </div>
                    `;
                    
                    skillsGrid.appendChild(hexagon);
                });
            }
            
            categoryTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    categoryTabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    displaySkills(tab.dataset.category);
                });
            });
            
            displaySkills();
        }

        // Initialize on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                initCarousel();
                initSkillsGrid();
                initParticles();
            });
        } else {
            initCarousel();
            initSkillsGrid();
            initParticles();
        }

        // Update carousel on window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                updateCarousel();
            }, 200);
        });

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.getElementById('navMenu');

        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', () => {
                const isActive = navMenu.classList.toggle('active');
                menuToggle.classList.toggle('active');
                document.body.classList.toggle('nav-menu-open', isActive);
            });

            // Fechar menu ao clicar em qualquer item/link dentro dele
            navMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    navMenu.classList.remove('active');
                    menuToggle.classList.remove('active');
                    document.body.classList.remove('nav-menu-open');
                });
            });
        }

        // Header scroll effect
        const header = document.getElementById('header');
        if (header) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 100) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        }

        // Smooth scrolling and active navigation
        const sections = document.querySelectorAll('section[id], [id="portfolio"]');
        const navLinks = document.querySelectorAll('.nav-link');

        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetSection = document.getElementById(targetId);
                
                if (targetSection) {
                    const headerHeight = header ? header.offsetHeight : 70;
                    const targetPosition = targetId === 'home'
                        ? 0
                        : (targetSection.getBoundingClientRect().top + window.pageYOffset - headerHeight - 14);
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    if (navMenu) navMenu.classList.remove('active');
                    if (menuToggle) menuToggle.classList.remove('active');
                    document.body.classList.remove('nav-menu-open');
                }
            });
        });

        // Update active navigation on scroll
        function updateActiveNav() {
            const headerEl = document.getElementById('header');
            const headerHeight = headerEl ? headerEl.offsetHeight : 70;
            const scrollPosition = window.scrollY + headerHeight + 50;
            
            if (window.scrollY < 180) {
                navLinks.forEach(link => {
                    const isHome = link.getAttribute('href') === '#home';
                    link.classList.toggle('active', isHome);
                });
                return;
            }

            let currentActiveId = null;
            sections.forEach(section => {
                const sectionTop = section.getBoundingClientRect().top + window.pageYOffset;
                const sectionHeight = section.offsetHeight;
                const sectionId = section.getAttribute('id');
                
                if (scrollPosition >= sectionTop - 35 && scrollPosition < sectionTop + sectionHeight) {
                    currentActiveId = sectionId;
                }
            });

            if (currentActiveId) {
                navLinks.forEach(link => {
                    const href = link.getAttribute('href').substring(1);
                    link.classList.toggle('active', href === currentActiveId);
                });
            }
        }

        window.addEventListener('scroll', updateActiveNav);

        // Animated counter for stats
        function animateCounter(element) {
            const target = parseInt(element.dataset.target);
            const duration = 2000;
            const step = target / (duration / 16);
            let current = 0;
            
            const counter = setInterval(() => {
                current += step;
                if (current >= target) {
                    element.textContent = target;
                    clearInterval(counter);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 16);
        }

        // Intersection Observer for stats animation
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumbers = entry.target.querySelectorAll('.stat-number');
                    statNumbers.forEach(number => {
                        if (!number.classList.contains('animated')) {
                            number.classList.add('animated');
                            animateCounter(number);
                        }
                    });
                }
            });
        }, observerOptions);

        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            observer.observe(statsSection);
        }

        // Form submission (null-safe)
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(contactForm);
                const data = Object.fromEntries(formData);
                alert(`Obrigado ${data.name}! Mensagem recebida.`);
                contactForm.reset();
            });
        }

        // Loading screen dismiss
        function hideLoader() {
            const loader = document.getElementById('loader');
            if (loader) {
                loader.classList.add('hidden');
                setTimeout(() => { loader.style.display = 'none'; }, 400);
            }
        }

        if (document.readyState === 'complete') {
            hideLoader();
        } else {
            window.addEventListener('load', hideLoader);
            document.addEventListener('DOMContentLoaded', () => setTimeout(hideLoader, 600));
        }
        setTimeout(hideLoader, 1200);

        // Parallax on hero disabled to preserve vertical document flow and prevent button clipping
        // window.addEventListener('scroll', () => { ... });