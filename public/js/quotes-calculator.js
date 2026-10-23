/**
 * NUME Quotes Calculator & Interactive Proposal Engine
 * Clean Vanilla JS adhering strictly to Tailwind CSS utility conventions.
 */

(function () {
    const $ = (id) => document.getElementById(id);
    const num = (id) => {
        const el = $(id);
        if (!el) return 0;
        const v = parseFloat(el.value);
        return isNaN(v) ? 0 : v;
    };
    const radio = (name) => document.querySelector(`input[name="${name}"]:checked`)?.value;
    const setRadio = (name, val) => {
        const el = document.querySelector(`input[name="${name}"][value="${val}"]`);
        if (el) el.checked = true;
    };
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const eur = (v) => (+v).toLocaleString('es-ES', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmt = (v) => (+v.toFixed(2)).toLocaleString('es-ES');

    window.QuoteApp = {
        state: null,
        activeTab: 'orc',

        init() {
            this.bindEvents();
            this.render();
        },

        bindEvents() {
            // Slider live update
            ['pre', 'gravacao', 'edicao', 'motion', 'prazo'].forEach((id) => {
                const el = $(id);
                if (el) {
                    el.addEventListener('input', () => this.render());
                }
            });

            // Form inputs
            const form = $('quote-form');
            if (form) {
                form.addEventListener('input', () => this.render());
                form.addEventListener('change', () => this.render());
            }

            // Sync videos with default edition hours
            const videosInput = $('videos');
            if (videosInput) {
                videosInput.addEventListener('input', () => {
                    const v = num('videos');
                    const ed = $('edicao');
                    if (ed) {
                        ed.value = Math.min(200, v * 3);
                    }
                    this.render();
                });
            }

            // Tab switching
            const tabOrc = $('tab-orc');
            const tabPdf = $('tab-pdf');
            if (tabOrc && tabPdf) {
                tabOrc.addEventListener('click', () => this.switchTab('orc'));
                tabPdf.addEventListener('click', () => this.switchTab('pdf'));
            }

            // Reset defaults
            const btnReset = $('btn-reset');
            if (btnReset) {
                btnReset.addEventListener('click', () => this.resetDefaults());
            }

            // Copy internal summary
            const btnCopiar = $('btn-copiar');
            if (btnCopiar) {
                btnCopiar.addEventListener('click', () => this.copySummary());
            }

            // Speech recognition
            const btnFalar = $('btn-falar');
            if (btnFalar) {
                this.setupSpeech(btnFalar);
            }

            // Sofi AI Estimator
            const btnEstimar = $('btn-estimar');
            if (btnEstimar) {
                btnEstimar.addEventListener('click', () => this.estimateWithSofi());
            }

            // Send Email (SMTP)
            const btnEnviar = $('btn-enviar-email');
            if (btnEnviar) {
                btnEnviar.addEventListener('click', () => this.sendEmailViaSmtp());
            }

            // Regenerate proposal text
            const btnRefazer = $('btn-refazer-email');
            if (btnRefazer) {
                btnRefazer.addEventListener('click', () => this.refreshEmailText());
            }

            // Client selector auto-fill
            const selCliente = $('cliente_select');
            if (selCliente) {
                selCliente.addEventListener('change', (e) => this.selectExistingClient(e.target.value));
            }
        },

        switchTab(tab) {
            this.activeTab = tab;
            const pOrc = $('painel-orc');
            const pPdf = $('painel-pdf');
            const tabOrc = $('tab-orc');
            const tabPdf = $('tab-pdf');

            if (tab === 'orc') {
                pOrc?.classList.remove('hidden');
                pPdf?.classList.add('hidden');
                tabOrc?.classList.add('text-orange-500', 'border-orange-500');
                tabOrc?.classList.remove('text-zinc-500', 'border-transparent');
                tabPdf?.classList.remove('text-orange-500', 'border-orange-500');
                tabPdf?.classList.add('text-zinc-500', 'border-transparent');
            } else {
                pOrc?.classList.add('hidden');
                pPdf?.classList.remove('hidden');
                tabPdf?.classList.add('text-orange-500', 'border-orange-500');
                tabPdf?.classList.remove('text-zinc-500', 'border-transparent');
                tabOrc?.classList.remove('text-orange-500', 'border-orange-500');
                tabOrc?.classList.add('text-zinc-500', 'border-transparent');
                this.refreshEmailText(false);
            }
        },

        readInputs() {
            const fmRadio = radio('fm') || '1';
            let filmmakers = parseInt(fmRadio, 10);
            if (fmRadio === '4') {
                filmmakers = Math.max(4, num('fm_n'));
            }

            const extras = [];
            for (let i = 1; i <= 3; i++) {
                const nEl = $('ex_nome_' + i);
                const vEl = $('ex_valor_' + i);
                if (nEl || vEl) {
                    const nome = (nEl?.value || '').trim();
                    const valor = num('ex_valor_' + i);
                    if (nome || valor > 0) {
                        extras.push({ nome, valor });
                    }
                }
            }

            return {
                client_name: ($('cliente')?.value || 'Cliente').trim(),
                client_contact: ($('contato')?.value || '').trim(),
                client_email: ($('email')?.value || '').trim(),
                project_title: ($('projeto')?.value || 'Vídeos para redes sociales').trim(),
                language: $('quote_language')?.value || 'es',
                request_text: ($('descricao')?.value || '').trim(),

                videos: Math.max(1, num('videos') || 1),
                pre: num('pre'),
                gravacao: num('gravacao'),
                edicao: num('edicao'),
                motion: num('motion'),
                filmmakers: Math.max(1, filmmakers),
                valor_hora: num('valor_hora'),
                valor_hora_editor: num('valor_hora_editor'),
                drone: $('drone')?.checked || false,
                drone_valor: num('drone_valor'),
                iva: num('iva'),
                margem: num('margem'),
                valor_unit: num('valor_unit'),
                adicional: num('adicional'),
                cambios: parseInt(radio('cambios') || '3', 10),
                prazo: Math.max(1, num('prazo') || 15),
                extras: extras,
            };
        },

        calculate(p) {
            const h_grav = p.gravacao * p.filmmakers;
            const horas = p.pre + h_grav + p.edicao + p.motion;
            const h_fm = p.pre + h_grav;
            const h_ed = p.edicao + p.motion;
            const custo_fm = h_fm * p.valor_hora;
            const custo_ed = h_ed * p.valor_hora_editor;
            const trabalho = custo_fm + custo_ed;
            const drone = p.drone ? p.drone_valor : 0;
            const extras_total = p.extras.reduce((acc, e) => acc + (e.valor || 0), 0);
            const videos_valor = p.videos * p.valor_unit;
            const preco_base = trabalho + videos_valor + drone + extras_total;
            const margem = preco_base * (p.margem / 100);
            const preco = preco_base + margem + p.adicional;
            const iva = preco * (p.iva / 100);
            const total = preco + iva;
            const sinal = Math.round(total * 50) / 100;
            const na_entrega = total - sinal;
            const por_video = p.videos ? preco / p.videos : 0;

            return {
                horas, h_grav, h_fm, h_ed, custo_fm, custo_ed, trabalho,
                drone, extras_total, videos_valor, preco_base, margem,
                adicional: p.adicional, preco, iva, total_com_iva: total,
                sinal, na_entrega, por_video
            };
        },

        render() {
            // Slider value bubbles
            const setVal = (id, text) => {
                const el = $('v_' + id);
                if (el) el.textContent = text;
            };

            setVal('pre', fmt(num('pre')) + ' h');
            setVal('gravacao', fmt(num('gravacao')) + ' h');
            setVal('edicao', fmt(num('edicao')) + ' h');
            setVal('motion', fmt(num('motion')) + ' h');
            setVal('prazo', num('prazo') + ' dias');

            // 4+ filmmakers field toggle
            const isFm4 = radio('fm') === '4';
            const fm4Wrap = $('lbl-fm4');
            if (fm4Wrap) {
                if (isFm4) {
                    fm4Wrap.classList.remove('hidden');
                } else {
                    fm4Wrap.classList.add('hidden');
                }
            }

            const p = this.readInputs();
            const r = this.calculate(p);
            this.state = { p, r };

            // Delivery estimate note
            const hintPrazo = $('hint-prazo');
            if (hintPrazo) {
                const deliveryDate = new Date(Date.now() + p.prazo * 864e5).toLocaleDateString('es-ES');
                hintPrazo.textContent = `Entrega en hasta ${p.prazo} días tras aprobación (${deliveryDate}).`;
            }

            const hintEdicao = $('hint-edicao');
            if (hintEdicao) {
                hintEdicao.textContent = `Referencia: ${p.videos} vídeos × 3 h = ${p.videos * 3} h de edición.`;
            }

            // Sticky monitor summary
            const resWrap = $('res-monitor');
            if (resWrap) {
                const row = (label, val, highlight = false, isTotal = false) => `
                    <div class="flex items-center justify-between py-2 border-b border-zinc-800 ${isTotal ? 'border-t-2 border-zinc-600 font-extrabold text-white text-base pt-3' : 'text-xs text-zinc-300'}">
                        <span>${esc(label)}</span>
                        <span class="font-mono ${highlight ? 'text-orange-400 font-bold' : ''}">${val}</span>
                    </div>
                `;

                let html = '';
                if (r.videos_valor > 0) {
                    html += row(`Vídeos (${p.videos} × ${eur(p.valor_unit)})`, eur(r.videos_valor));
                }
                html += row(`Filmmakers (${fmt(r.h_fm)} h × ${eur(p.valor_hora)})`, eur(r.custo_fm));
                html += row(`Editor (${fmt(r.h_ed)} h × ${eur(p.valor_hora_editor)})`, eur(r.custo_ed));
                if (r.drone > 0) {
                    html += row('Imágenes con Dron', eur(r.drone));
                }
                p.extras.forEach((e, idx) => {
                    html += row(e.nome || `Extra ${idx + 1}`, eur(e.valor));
                });
                if (r.margem > 0) {
                    html += row(`Margen interna (${fmt(p.margem)}%)`, eur(r.margem));
                }
                if (r.adicional !== 0) {
                    html += row('Ajuste adicional', (r.adicional > 0 ? '+' : '') + eur(r.adicional));
                }
                html += row('Subtotal antes de IVA', eur(r.preco), true);
                html += row('IVA (' + fmt(p.iva) + '%)', eur(r.iva));
                html += row('Total Presupuesto', eur(r.total_com_iva), false, true);
                html += `
                    <p class="text-[11px] text-zinc-500 mt-2">
                        ${fmt(r.horas)} h de trabajo total · ${eur(r.por_video)} por vídeo sin IVA
                    </p>
                `;
                resWrap.innerHTML = html;
            }

            // Top flag summary
            const flag = $('header-flag');
            if (flag) {
                flag.textContent = `${p.filmmakers} filmmaker${p.filmmakers > 1 ? 's' : ''} · ${fmt(r.horas)} h · ${eur(r.preco)} + ${fmt(p.iva)}% IVA`;
            }

            // Render Preview Proposal in second tab
            this.renderProposalPreview(p, r);
        },

        renderProposalPreview(p, r) {
            const propEl = $('preview-proposta');
            if (!propEl) return;

            const n = p.videos;
            const deliverables = [`${n} ${n === 1 ? 'vídeo' : 'vídeos'}${p.project_title ? ' · ' + p.project_title : ''}`];
            if (p.edicao > 0) deliverables.push('Edición y postproducción');
            if (p.motion > 0) deliverables.push('Motion graphics, VFX o IA');
            if (p.drone) deliverables.push('Imágenes aéreas con dron');
            p.extras.forEach((e, k) => {
                if (e.nome || e.valor) deliverables.push(e.nome || `Servicio extra ${k + 1}`);
            });
            deliverables.push(`${p.cambios} ${p.cambios === 1 ? 'cambio incluido' : 'cambios incluidos'} en cada vídeo`);

            const today = new Date().toLocaleDateString('es-ES');
            const valid = new Date(Date.now() + 10 * 864e5).toLocaleDateString('es-ES');

            propEl.innerHTML = `
                <div class="bg-white text-zinc-900 rounded-xl p-6 border border-zinc-200 shadow-sm space-y-4 text-xs font-sans">
                    <div class="flex justify-between items-start border-b border-orange-600 pb-3">
                        <div>
                            <div class="text-xl font-black text-orange-600 tracking-tight">NUME</div>
                            <div class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider">Producción Audiovisual</div>
                        </div>
                        <div class="text-right text-[11px] text-zinc-600">
                            <b class="text-orange-600 text-sm block">PRESUPUESTO</b>
                            Fecha: ${today}<br>
                            Válido hasta: ${valid}
                        </div>
                    </div>

                    <div>
                        <div class="text-[10px] font-black uppercase text-orange-600 tracking-wider">Cliente</div>
                        <div class="font-bold text-zinc-900 text-sm">${esc(p.client_name)}</div>
                        ${p.client_contact ? `<div class="text-zinc-600">${esc(p.client_contact)}</div>` : ''}
                        ${p.client_email ? `<div class="text-zinc-600">${esc(p.client_email)}</div>` : ''}
                        <div class="text-zinc-600">Proyecto: ${esc(p.project_title)}</div>
                    </div>

                    <div>
                        <div class="text-[10px] font-black uppercase text-orange-600 tracking-wider">Qué vamos a entregar</div>
                        <ul class="list-disc pl-5 space-y-1 mt-1 text-zinc-800">
                            ${deliverables.map(d => `<li>${esc(d)}</li>`).join('')}
                        </ul>
                    </div>

                    <div class="border-t-2 border-zinc-900 pt-2 flex justify-between items-baseline">
                        <span class="text-sm font-bold text-zinc-900">Total</span>
                        <span class="text-base font-black text-orange-600 font-mono">
                            ${eur(r.preco)} + ${fmt(p.iva)}% IVA
                        </span>
                    </div>

                    <div class="space-y-1 text-zinc-700 pt-2 border-t border-zinc-200">
                        <div><strong>Plazo de entrega:</strong> Hasta ${p.prazo} días tras la aprobación.</div>
                        <div><strong>Forma de pago:</strong> 50% a la aprobación, para reservar la fecha · 50% a la entrega.</div>
                        <div class="text-[10px] text-zinc-500">Presupuesto válido durante 10 días, hasta el ${valid}.</div>
                    </div>
                </div>
            `;
        },

        refreshEmailText(force = false) {
            if (!this.state) return;
            const { p, r } = this.state;

            const n = p.videos;
            const partes = [];
            if (p.drone) partes.push('imágenes aéreas con dron');
            if (p.motion > 0) partes.push('motion graphics');
            p.extras.forEach(e => {
                if (e.nome) partes.push(e.nome.toLowerCase());
            });

            const lista = partes.length > 1
                ? partes.slice(0, -1).join(', ') + ' y ' + partes.at(-1)
                : (partes[0] || '');

            const desc = `${n} ${n === 1 ? 'vídeo editado' : 'vídeos editados'}${lista ? ', con ' + lista : ''} y ${p.cambios} ${p.cambios === 1 ? 'cambio incluido' : 'cambios incluidos'} en cada vídeo`;

            const valid = new Date(Date.now() + 10 * 864e5).toLocaleDateString('es-ES');
            const h = new Date().getHours();
            const saudacao = h < 14 ? 'Buenos días' : (h < 21 ? 'Buenas tardes' : 'Buenas noches');

            const defaultAsunto = `Presupuesto ${p.project_title ? p.project_title : 'Audiovisual'} · NUME`;
            const defaultCorpo = `${saudacao}${p.client_name ? ', ' + p.client_name : ''}:

Gracias por contar con NUME. Le enviamos adjunto el presupuesto para «${p.project_title}»: ${desc}.

El importe total es de ${eur(r.preco)} + ${fmt(p.iva)}% IVA y el plazo de entrega es de hasta ${p.prazo} días tras la aprobación. La forma de pago es 50% a la aprobación, para reservar la fecha, y 50% a la entrega.

El presupuesto es válido hasta el ${valid}. Quedamos a su entera disposición para cualquier duda o ajuste en la producción.

Un cordial saludo,
NUME · producción audiovisual estratégica`;

            const emPara = $('em_para');
            const emAssunto = $('em_assunto');
            const emCorpo = $('em_corpo');

            if (emPara && (force || !emPara.value)) {
                emPara.value = p.client_email || '';
            }
            if (emAssunto && (force || !emAssunto.value)) {
                emAssunto.value = defaultAsunto;
            }
            if (emCorpo && (force || !emCorpo.value)) {
                emCorpo.value = defaultCorpo;
            }
        },

        selectExistingClient(clienteId) {
            if (!clienteId) return;
            const sel = $('cliente_select');
            const opt = sel?.querySelector(`option[value="${clienteId}"]`);
            if (!opt) return;

            const name = opt.getAttribute('data-name');
            const email = opt.getAttribute('data-email');
            const phone = opt.getAttribute('data-phone');

            if (name && $('cliente')) $('cliente').value = name;
            if (email && $('email')) $('email').value = email;
            if (phone && $('contato')) $('contato').value = phone;

            this.render();
        },

        copySummary() {
            if (!this.state) return;
            const { p, r } = this.state;
            const out = [
                `Orçamento interno · ${p.client_name} · ${p.project_title}`,
                `${p.videos} vídeos · ${p.filmmakers} filmmakers`,
                '',
                `Filmmakers: (pré ${fmt(p.pre)} h + gravação ${fmt(p.gravacao)} h × ${p.filmmakers}) = ${fmt(r.h_fm)} h × ${eur(p.valor_hora)} = ${eur(r.custo_fm)}`,
                `Editor: (edição ${fmt(p.edicao)} h + motion ${fmt(p.motion)} h) = ${fmt(r.h_ed)} h × ${eur(p.valor_hora_editor)} = ${eur(r.custo_ed)}`,
            ];
            if (p.drone) out.push(`Drone: ${eur(r.drone)}`);
            p.extras.forEach((e, k) => out.push(`${e.nome || 'Serviço extra ' + (k + 1)}: ${eur(e.valor)}`));
            if (r.videos_valor > 0) out.splice(3, 0, `Vídeos: ${p.videos} × ${eur(p.valor_unit)} = ${eur(r.videos_valor)}`);

            out.push('');
            if (r.margem > 0) {
                out.push(`Subtotal antes da margem: ${eur(r.preco_base)}`);
                out.push(`Margem de ganho ${fmt(p.margem)}% (interna): ${eur(r.margem)}`);
            }
            if (r.adicional !== 0) {
                out.push(`Ajuste adicional (interno): ${eur(r.adicional)}`);
            }
            out.push(`Total: ${eur(r.preco)} + ${fmt(p.iva)}% IVA (${eur(r.por_video)} por vídeo)`);
            out.push(`Prazo: até ${p.prazo} dias após aprovação.`);
            out.push('Pagamento: 50% na aprovação; 50% na entrega.');

            const text = out.join('\n');
            navigator.clipboard.writeText(text).then(() => {
                this.toast('toast-orc', 'Orçamento copiado para a área de transferência!');
            }).catch(() => {
                this.toast('toast-orc', 'Selecione e copie o texto manualmente.');
            });
        },

        setupSpeech(btn) {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) {
                btn.classList.add('hidden');
                return;
            }
            let rec = null;
            btn.addEventListener('click', () => {
                if (rec) {
                    rec.stop();
                    return;
                }
                rec = new SR();
                rec.lang = 'pt-BR';
                rec.continuous = true;
                rec.interimResults = true;

                const txtEl = $('descricao');
                const prev = txtEl?.value || '';

                rec.onresult = (e) => {
                    let text = '';
                    for (let i = e.resultIndex; i < e.results.length; i++) {
                        text += e.results[i][0].transcript;
                    }
                    if (txtEl) {
                        txtEl.value = (prev ? prev + '\n' : '') + text;
                    }
                };

                rec.onerror = () => {
                    this.toast('estado-estimar', 'Microfone não autorizado. Digite o pedido na caixa.');
                };

                rec.onend = () => {
                    rec = null;
                    btn.textContent = '🎙 Falar o pedido';
                    btn.classList.remove('bg-rose-900/50', 'text-rose-300');
                };

                rec.start();
                btn.textContent = '⏹ Parar gravação';
                btn.classList.add('bg-rose-900/50', 'text-rose-300');
            });
        },

        async estimateWithSofi() {
            const txtEl = $('descricao');
            const desc = (txtEl?.value || '').trim();
            if (!desc) {
                this.toast('estado-estimar', 'Escreva ou dite o pedido do cliente primeiro.');
                return;
            }

            const btn = $('btn-estimar');
            const statusEl = $('estado-estimar');
            if (btn) btn.disabled = true;
            if (statusEl) {
                statusEl.textContent = 'A Sofi está analisando o pedido e calculando a esteira...';
                statusEl.classList.remove('text-rose-400');
                statusEl.classList.add('text-orange-400');
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const res = await fetch('/orcamentos/estimar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ description: desc }),
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Falha ao processar pedido.');
                }

                // Apply parsed values
                const p = data.reading.pedido;
                if (p.videos && $('videos')) $('videos').value = p.videos;
                if (p.pre !== undefined && $('pre')) $('pre').value = p.pre;
                if (p.gravacao !== undefined && $('gravacao')) $('gravacao').value = p.gravacao;
                if (p.edicao !== undefined && $('edicao')) $('edicao').value = p.edicao;
                if (p.motion !== undefined && $('motion')) $('motion').value = p.motion;
                if (p.filmmakers !== undefined) {
                    if (p.filmmakers >= 4) {
                        setRadio('fm', 4);
                        if ($('fm_n')) $('fm_n').value = p.filmmakers;
                    } else {
                        setRadio('fm', p.filmmakers);
                    }
                }
                if (p.drone !== undefined && $('drone')) $('drone').checked = !!p.drone;
                if (p.cliente && $('cliente')) $('cliente').value = p.cliente;
                if (p.projeto && $('projeto')) $('projeto').value = p.projeto;

                // Save ai_reading for backend persist
                const aiReadingInput = $('ai_reading');
                if (aiReadingInput) {
                    aiReadingInput.value = JSON.stringify(data.reading);
                }

                this.render();

                // Display Sofi reading card
                const leituraEl = $('leitura-sofi');
                if (leituraEl) {
                    leituraEl.innerHTML = `
                        <div class="p-3.5 rounded-xl bg-orange-950/30 border border-orange-800/40 space-y-2 mt-3">
                            <div class="flex items-center gap-2 text-xs font-bold text-orange-400">
                                <span>✨</span> <span>${esc(data.reading.resumo)}</span>
                            </div>
                            ${data.reading.suposicoes && data.reading.suposicoes.length ? `
                                <div>
                                    <div class="text-[10px] font-bold uppercase text-zinc-500">Premissas assumidas:</div>
                                    <ul class="text-[11px] text-zinc-300 list-disc pl-4 space-y-0.5 mt-0.5">
                                        ${data.reading.suposicoes.map(s => `<li>${esc(s)}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                            ${data.reading.duvidas && data.reading.duvidas.length ? `
                                <div>
                                    <div class="text-[10px] font-bold uppercase text-amber-500">Para confirmar com o cliente:</div>
                                    <ul class="text-[11px] text-zinc-400 list-disc pl-4 space-y-0.5 mt-0.5">
                                        ${data.reading.duvidas.map(d => `<li>${esc(d)}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    leituraEl.classList.remove('hidden');
                }

                if (statusEl) {
                    statusEl.textContent = 'Parâmetros preenchidos automaticamente pela Sofi!';
                    statusEl.classList.remove('text-orange-400');
                    statusEl.classList.add('text-emerald-400');
                }
            } catch (err) {
                if (statusEl) {
                    statusEl.textContent = err.message || 'Erro ao conectar à Sofi. Ajuste manualmente.';
                    statusEl.classList.remove('text-orange-400', 'text-emerald-400');
                    statusEl.classList.add('text-rose-400');
                }
            } finally {
                if (btn) btn.disabled = false;
            }
        },

        async sendEmailViaSmtp() {
            const quoteUuid = $('quote_uuid')?.value;
            if (!quoteUuid) {
                this.toast('toast-envio', 'Salve o orçamento primeiro antes de enviar o e-mail oficial.');
                return;
            }

            const email = ($('em_para')?.value || '').trim();
            if (!email || !email.includes('@')) {
                this.toast('toast-envio', 'Preencha um endereço de e-mail válido para o cliente.');
                return;
            }

            const btn = $('btn-enviar-email');
            if (btn) btn.disabled = true;
            this.toast('toast-envio', 'Enviando proposta em PDF pelo servidor SMTP...', 'text-orange-400');

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const res = await fetch(`/orcamentos/${quoteUuid}/enviar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email,
                        subject: ($('em_assunto')?.value || '').trim(),
                        message: ($('em_corpo')?.value || '').trim(),
                    }),
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Falha ao enviar e-mail.');
                }

                this.toast('toast-envio', data.message || 'E-mail enviado com sucesso com anexo PDF!', 'text-emerald-400');
            } catch (err) {
                this.toast('toast-envio', err.message || 'Erro ao enviar e-mail. Verifique a configuração SMTP.', 'text-rose-400');
            } finally {
                if (btn) btn.disabled = false;
            }
        },

        resetDefaults() {
            if ($('videos')) $('videos').value = 4;
            if ($('pre')) $('pre').value = 2;
            if ($('gravacao')) $('gravacao').value = 4;
            if ($('edicao')) $('edicao').value = 12;
            if ($('motion')) $('motion').value = 0;
            setRadio('fm', 2);
            if ($('fm_n')) $('fm_n').value = 4;
            if ($('drone')) $('drone').checked = false;
            if ($('drone_valor')) $('drone_valor').value = 150;
            if ($('iva')) $('iva').value = 21;
            if ($('margem')) $('margem').value = 0;
            if ($('valor_unit')) $('valor_unit').value = 0;
            if ($('adicional')) $('adicional').value = 0;
            setRadio('cambios', 3);
            if ($('prazo')) $('prazo').value = 15;

            for (let i = 1; i <= 3; i++) {
                if ($('ex_nome_' + i)) $('ex_nome_' + i).value = '';
                if ($('ex_valor_' + i)) $('ex_valor_' + i).value = '';
            }

            this.render();
            this.toast('toast-orc', 'Valores restaurados para os padrões.');
        },

        toast(id, msg, colorClass = 'text-orange-400') {
            const el = $(id);
            if (!el) return;
            el.className = `text-xs font-semibold mt-2 transition-all ${colorClass}`;
            el.textContent = msg;
            setTimeout(() => {
                if (el.textContent === msg) el.textContent = '';
            }, 6000);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.QuoteApp.init();
    });
})();
