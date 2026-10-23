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
            { name: 'React.js', icon: '⚛️', level: 95, category: 'frontend' },
            { name: 'Node.js', icon: '🟢', level: 90, category: 'backend' },
            { name: 'TypeScript', icon: '📘', level: 88, category: 'frontend' },
            { name: 'AWS', icon: '☁️', level: 92, category: 'cloud' },
            { name: 'Docker', icon: '🐳', level: 85, category: 'cloud' },
            { name: 'Python', icon: '🐍', level: 93, category: 'backend' },
            { name: 'Kubernetes', icon: '☸️', level: 82, category: 'cloud' },
            { name: 'GraphQL', icon: '◈', level: 87, category: 'backend' },
            { name: 'TensorFlow', icon: '🤖', level: 78, category: 'emerging' },
            { name: 'Blockchain', icon: '🔗', level: 75, category: 'emerging' },
            { name: 'Vue.js', icon: '💚', level: 85, category: 'frontend' },
            { name: 'MongoDB', icon: '🍃', level: 90, category: 'backend' }
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

        // Initialize carousel
        let currentIndex = 0;
        const carousel = document.getElementById('carousel');
        const indicatorsContainer = document.getElementById('indicators');

        function createCarouselItem(data, index) {
            const item = document.createElement('div');
            item.className = 'carousel-item';
            item.dataset.index = index;
            
            const isVertical = data.is_vertical !== false;
            const escapedTitle = (data.title || '').replace(/'/g, "\\'");
            const videoUrl = data.video_url || '#';
            const embedUrl = data.embed_url || '';

            if (isVertical) {
                item.classList.add('is-vertical');
                const categoryName = (data.tech && data.tech[1]) || 'Reels';

                item.innerHTML = `
                    <div class="card">
                        <img src="${data.image}" alt="${data.title}" class="shorts-backdrop">
                        <div class="shorts-gradient-overlay"></div>
                        <div class="shorts-top-bar">
                            <span class="shorts-badge">📱 9:16 SHORTS</span>
                            <span class="shorts-category">${categoryName}</span>
                        </div>
                        <div class="shorts-center-play">▶</div>
                        <div class="shorts-bottom-info">
                            <h3 class="shorts-title">${data.title}</h3>
                            <div class="shorts-meta">
                                <span style="color: #fb923c; font-weight: 800;">${(window.__ ? window.__('retention_badge', '⚡ 94% Retenção') : '⚡ 94% Retenção')}</span>
                                <span>·</span>
                                <span>NUME 4K</span>
                            </div>
                            <button type="button" class="shorts-cta-btn">
                                <span>${(window.__ ? window.__('watch_shorts', 'Assistir Shorts') : 'Assistir Shorts')}</span>
                                <span>▶</span>
                            </button>
                        </div>
                    </div>
                `;
            } else {
                item.classList.add('is-horizontal');
                const techBadges = (data.tech || []).map(tech => 
                    `<span class="tech-badge">${tech}</span>`
                ).join('');

                item.innerHTML = `
                    <div class="card">
                        <div class="card-number">0${data.id || (index + 1)}</div>
                        <div class="card-image" style="height: 190px; overflow: hidden; border-radius: 14px; position: relative;">
                            <img src="${data.image}" alt="${data.title}" style="width: 100%; height: 100%; object-fit: cover;">
                            <div style="position: absolute; top: 10px; left: 10px; z-index: 2;">
                                <span style="background: rgba(24, 24, 27, 0.9); color: #e4e4e7; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 2px 8px rgba(0,0,0,0.5); display: inline-flex; align-items: center; gap: 4px; letter-spacing: 0.5px;">🎬 16:9 WIDE</span>
                            </div>
                            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.35);">
                                <div style="width: 48px; height: 48px; border-radius: 50%; background: #ea580c; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 0 20px rgba(234, 88, 12, 0.6); transition: transform 0.2s ease;">▶</div>
                            </div>
                        </div>
                        <h3 class="card-title" style="margin-top: 15px; font-size: 18px;">${data.title}</h3>
                        <p class="card-description" style="font-size: 13px; line-height: 1.4;">${data.description || ''}</p>
                        <div class="card-tech">${techBadges}</div>
                        <button type="button" class="card-cta">${(window.__ ? window.__('watch_video', 'Assistir Vídeo') : 'Assistir Vídeo')} ▶</button>
                    </div>
                `;
            }

            // Ação unificada Click + Touch
            function triggerCardAction(e) {
                if (e) {
                    e.stopPropagation();
                }
                const currentIdx = typeof currentIndex !== 'undefined' ? currentIndex : 0;
                const thisIdx = parseInt(item.dataset.index, 10);
                
                if (thisIdx === currentIdx) {
                    if (window.openVideoModal) {
                        window.openVideoModal(videoUrl, data.title, isVertical, embedUrl);
                    }
                } else {
                    if (typeof goToSlide === 'function') {
                        goToSlide(thisIdx);
                    }
                }
            }

            // Click padrão
            item.addEventListener('click', triggerCardAction);

            // Touch dedicado para mobile
            let tapStartX = 0;
            let tapStartY = 0;
            let tapStartTime = 0;
            item.addEventListener('touchstart', (e) => {
                if (e.touches.length === 1) {
                    tapStartX = e.touches[0].clientX;
                    tapStartY = e.touches[0].clientY;
                    tapStartTime = Date.now();
                }
            }, { passive: true });

            item.addEventListener('touchend', (e) => {
                if (e.changedTouches.length === 1) {
                    const diffX = Math.abs(e.changedTouches[0].clientX - tapStartX);
                    const diffY = Math.abs(e.changedTouches[0].clientY - tapStartY);
                    const duration = Date.now() - tapStartTime;
                    // Se foi um toque curto (< 400ms e < 15px de arraste), dispara a ação
                    if (diffX < 15 && diffY < 15 && duration < 400) {
                        triggerCardAction(e);
                    }
                }
            }, { passive: false });
            
            return item;
        }

        function rebuildCarousel() {
            const carousel = document.getElementById('carousel');
            const indicatorsContainer = document.getElementById('indicators');
            if (!carousel || !indicatorsContainer) return;

            carousel.innerHTML = '';
            indicatorsContainer.innerHTML = '';
            currentIndex = 0;

            if (portfolioData.length === 0) {
                carousel.innerHTML = `
                    <div style="text-align: center; color: #a1a1aa; padding: 60px 20px; font-size: 14px; width: 100%;">
                        ${(window.__ ? window.__('no_videos_found', '⚡ Nenhum vídeo encontrado para este formato no momento.') : '⚡ Nenhum vídeo encontrado para este formato no momento.')}
                    </div>
                `;
                return;
            }

            portfolioData.forEach((data, index) => {
                const item = createCarouselItem(data, index);
                carousel.appendChild(item);
                
                const indicator = document.createElement('div');
                indicator.className = 'indicator';
                if (index === 0) indicator.classList.add('active');
                indicator.dataset.index = index;
                indicator.addEventListener('click', () => goToSlide(index));
                indicatorsContainer.appendChild(indicator);
            });
            
            updateCarousel();
        }

        window.filterPortfolio = function(filterType, btnEl) {
            // Atualiza botões
            const buttons = document.querySelectorAll('.portfolio-tab-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
                btn.style.background = 'rgba(18, 18, 20, 0.75)';
                btn.style.color = '#a1a1aa';
                btn.style.borderColor = 'rgba(255, 255, 255, 0.08)';
                btn.style.boxShadow = 'none';
            });

            if (btnEl) {
                btnEl.classList.add('active');
                btnEl.style.background = 'linear-gradient(135deg, #ea580c, #f97316)';
                btnEl.style.color = '#ffffff';
                btnEl.style.borderColor = 'rgba(255, 255, 255, 0.25)';
                btnEl.style.boxShadow = '0 6px 20px rgba(234, 88, 12, 0.45)';
            }

            // Filtra os dados
            if (filterType === 'vertical') {
                portfolioData = rawPortfolioData.filter(item => item.is_vertical !== false);
            } else if (filterType === 'horizontal') {
                portfolioData = rawPortfolioData.filter(item => item.is_vertical === false);
            } else {
                portfolioData = [...rawPortfolioData];
            }

            rebuildCarousel();
        };

        function initCarousel() {
            rebuildCarousel();
        }

        function updateCarousel() {
            const items = document.querySelectorAll('.carousel-item');
            const indicators = document.querySelectorAll('.indicator');
            const totalItems = items.length;
            const isSmallMobile = window.innerWidth <= 480;
            const isMobile = window.innerWidth <= 768;
            const isTablet = window.innerWidth <= 1024;
            
            // Adjust spacing based on orientation and screen size
            const hasVertical = items.length > 0 && items[0].classList.contains('is-vertical');
            let spacing1 = hasVertical ? 310 : 410;
            let spacing2 = hasVertical ? 480 : 630;
            let spacing3 = hasVertical ? 630 : 770;
            
            if (isSmallMobile) {
                spacing1 = hasVertical ? 150 : 200;
                spacing2 = hasVertical ? 250 : 310;
                spacing3 = hasVertical ? 350 : 420;
            } else if (isMobile) {
                spacing1 = hasVertical ? 210 : 270;
                spacing2 = hasVertical ? 330 : 410;
                spacing3 = hasVertical ? 450 : 540;
            } else if (isTablet) {
                spacing1 = hasVertical ? 260 : 340;
                spacing2 = hasVertical ? 410 : 520;
                spacing3 = hasVertical ? 540 : 650;
            }

            items.forEach((item, index) => {
                // Calculate relative position
                let offset = index - currentIndex;
                
                // Wrap around for continuous rotation
                if (offset > totalItems / 2) {
                    offset -= totalItems;
                } else if (offset < -totalItems / 2) {
                    offset += totalItems;
                }
                
                const absOffset = Math.abs(offset);
                const sign = offset < 0 ? -1 : 1;
                
                // Reset transform
                item.style.transform = '';
                item.style.opacity = '';
                item.style.zIndex = '';
                item.style.transition = 'all 0.8s cubic-bezier(0.4, 0.0, 0.2, 1)';
                
                if (absOffset === 0) {
                    // Center item: foco absoluto para touch e clique
                    item.style.transform = 'translate(-50%, -50%) translateZ(0) scale(1)';
                    item.style.opacity = '1';
                    item.style.zIndex = '50';
                    item.style.pointerEvents = 'auto';
                } else if (absOffset === 1) {
                    // Side items
                    const translateX = sign * spacing1;
                    const rotation = isMobile ? 25 : 30;
                    const scale = isMobile ? 0.88 : 0.85;
                    item.style.transform = `translate(-50%, -50%) translateX(${translateX}px) translateZ(-200px) rotateY(${-sign * rotation}deg) scale(${scale})`;
                    item.style.opacity = '0.8';
                    item.style.zIndex = '20';
                    item.style.pointerEvents = 'auto';
                } else if (absOffset === 2) {
                    // Further side items
                    const translateX = sign * spacing2;
                    const rotation = isMobile ? 35 : 40;
                    const scale = isMobile ? 0.75 : 0.7;
                    item.style.transform = `translate(-50%, -50%) translateX(${translateX}px) translateZ(-350px) rotateY(${-sign * rotation}deg) scale(${scale})`;
                    item.style.opacity = '0.5';
                    item.style.zIndex = '10';
                    item.style.pointerEvents = 'auto';
                } else if (absOffset === 3) {
                    // Even further items
                    const translateX = sign * spacing3;
                    const rotation = isMobile ? 40 : 45;
                    const scale = isMobile ? 0.65 : 0.6;
                    item.style.transform = `translate(-50%, -50%) translateX(${translateX}px) translateZ(-450px) rotateY(${-sign * rotation}deg) scale(${scale})`;
                    item.style.opacity = '0.3';
                    item.style.zIndex = '5';
                    item.style.pointerEvents = 'auto';
                } else {
                    // Hidden items (behind)
                    item.style.transform = 'translate(-50%, -50%) translateZ(-500px) scale(0.5)';
                    item.style.opacity = '0';
                    item.style.zIndex = '1';
                    item.style.pointerEvents = 'none';
                }
            });
            
            // Update indicators
            indicators.forEach((indicator, index) => {
                indicator.classList.toggle('active', index === currentIndex);
            });
        }

        function nextSlide() {
            if (!portfolioData || portfolioData.length <= 1) return;
            currentIndex = (currentIndex + 1) % portfolioData.length;
            updateCarousel();
        }

        function prevSlide() {
            if (!portfolioData || portfolioData.length <= 1) return;
            currentIndex = (currentIndex - 1 + portfolioData.length) % portfolioData.length;
            updateCarousel();
        }

        function goToSlide(index) {
            if (!portfolioData || portfolioData.length === 0) return;
            currentIndex = index;
            updateCarousel();
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

        // Event listeners
        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        if (nextBtn) nextBtn.addEventListener('click', nextSlide);
        if (prevBtn) prevBtn.addEventListener('click', prevSlide);

        // Auto-rotate carousel inteligente com pausa em hover/touch/modal
        let autoRotateTimer = null;
        function isModalOpen() {
            const modal = document.getElementById('storefront-video-modal');
            return modal && modal.style.display === 'flex';
        }

        function startAutoRotate() {
            stopAutoRotate();
            autoRotateTimer = setInterval(() => {
                if (isModalOpen()) return;
                nextSlide();
            }, 6000);
        }

        function stopAutoRotate() {
            if (autoRotateTimer) {
                clearInterval(autoRotateTimer);
                autoRotateTimer = null;
            }
        }

        startAutoRotate();

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (isModalOpen()) return;
            if (e.key === 'ArrowLeft') prevSlide();
            if (e.key === 'ArrowRight') nextSlide();
        });

        // Touch swipe support for mobile
        const carouselContainer = document.querySelector('.carousel-container');
        if (carouselContainer) {
            carouselContainer.addEventListener('mouseenter', stopAutoRotate);
            carouselContainer.addEventListener('mouseleave', startAutoRotate);

            let touchStartX = 0;
            let touchStartY = 0;
            let touchEndX = 0;
            let touchEndY = 0;

            carouselContainer.addEventListener('touchstart', (e) => {
                stopAutoRotate();
                if (e.touches.length === 1) {
                    touchStartX = e.touches[0].clientX;
                    touchStartY = e.touches[0].clientY;
                }
            }, { passive: true });

            carouselContainer.addEventListener('touchend', (e) => {
                if (e.changedTouches.length === 1) {
                    touchEndX = e.changedTouches[0].clientX;
                    touchEndY = e.changedTouches[0].clientY;
                    const diffX = touchEndX - touchStartX;
                    const diffY = touchEndY - touchStartY;

                    // Se foi um swipe horizontal claro (> 40px e mais horizontal que vertical)
                    if (Math.abs(diffX) > 40 && Math.abs(diffX) > Math.abs(diffY)) {
                        if (diffX < 0) {
                            nextSlide();
                        } else {
                            prevSlide();
                        }
                    }
                }
                setTimeout(startAutoRotate, 7000);
            }, { passive: true });
        }

        // Update carousel on window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                updateCarousel();
            }, 250);
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
                    const targetPosition = targetSection.offsetTop - headerHeight;
                    
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
            const scrollPosition = window.scrollY + 100;
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                const sectionId = section.getAttribute('id');
                
                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        const href = link.getAttribute('href').substring(1);
                        if (href === sectionId) {
                            link.classList.add('active');
                        }
                    });
                }
            });
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