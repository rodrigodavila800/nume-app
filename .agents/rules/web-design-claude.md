# Diretrizes de Web Design & Estética Visual Claude (Tailwind & Blade)

**Objetivo:** Estabelecer o padrão oficial de excelência visual, design de interface (UI/UX) e estética para qualquer desenvolvimento web no ecossistema **Nume App**, combinando os princípios de design refinado do Claude/Anthropic com as regras do Laravel 11 e Tailwind CSS.

---

## 1. Princípios Fundamentais de Estética (The Wow Factor)

1. **Impacto Visual Imediato:**
   - Toda tela desenvolvida deve causar uma impressão premium desde o primeiro segundo. Evite layouts esqueléticos ou aparências de "MVP genérico".
   - Use profundidade visual com camadas estruturadas: background base, superfícies de cartões (`bg-white` ou `bg-slate-900`), elevações sutis (`shadow-sm`, `shadow-md`, `shadow-xl/5`) e bordas nítidas com baixa opacidade (`border-slate-200 dark:border-slate-800`).

2. **Cores Harmoniosas & Paletas Curadas:**
   - **Proibido cores primárias puras ou cruas** (ex: vermelho puro `#ff0000`, azul elétrico `#0000ff`).
   - Adote paletas coordenadas e modernas baseadas em Slate/Zinc para neutros, com toques estratégicos de cores de destaque:
     - Primária/Ação: Indigo / Violet (`indigo-600`, hover `indigo-700`, foco `ring-indigo-500`)
     - Sucesso/Aprovado/Pago: Emerald / Teal (`emerald-500`, `emerald-50`, `text-emerald-700`)
     - Alerta/Pendente/Atenção: Amber / Orange (`amber-500`, `amber-50`, `text-amber-700`)
     - Perigo/Cancelado/Erro: Rose / Red suave (`rose-500`, `rose-50`, `text-rose-700`)

3. **Tipografia Moderna & Hierarquia Rigorosa:**
   - Fontes limpas e legíveis (Inter, Plus Jakarta Sans, Outfit ou IBM Plex Sans).
   - Hierarquia clara:
     - Títulos principais (`h1`): `text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white`
     - Subtítulos e seções (`h2`/`h3`): `text-lg sm:text-xl font-semibold text-slate-800 dark:text-slate-100`
     - Textos de apoio e labels: `text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400`
     - Dados tabulares ou métricas/código: `font-mono text-sm tracking-tight`

4. **Glassmorphism & Efeitos Translúcidos:**
   - Em cabeçalhos fixos, filtros, barras de navegação ou modais, utilize desfoque de fundo:
     `backdrop-blur-md bg-white/80 dark:bg-slate-900/80 border-b border-slate-200/80 dark:border-slate-800/80`

---

## 2. Regra de Ouro: Zero CSS Inline

- **É expressamente proibido o uso de `style="..."` dentro das views Blade.**
- **Motivo:** O CSS inline fragmenta a manutenção, impede temas dinâmicos, quebra políticas CSP (Content Security Policy) e degrada a performance de renderização.
- Toda estilização deve ser feita via:
  1. Classes utilitárias nativas do **Tailwind CSS**.
  2. Classes semânticas centralizadas no arquivo de folha de estilos do sistema (`public/css/app-shell.css` ou CSS do tema respectivo).

---

## 3. Micro-interações, Transições e Dinâmica

Uma interface viva engaja o usuário e transmite robustez técnica:
1. **Botões e Ações:**
   - Sempre incluir transições suaves: `transition-all duration-200 ease-in-out`
   - Estados de foco e ativação visíveis: `focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500`
   - Leve elevação ou escala em hover: `hover:-translate-y-0.5 active:translate-y-0`
2. **Cards e Itens de Lista:**
   - Feedback ao passar o cursor: `hover:border-indigo-300 dark:hover:border-indigo-700 hover:shadow-md transition-all duration-200`
3. **Badges e Indicadores de Status:**
   - Padrão moderno em formato de pílula com ponto pulsante ou cor suave:
     ```html
     <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
       <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
       Aprovado
     </span>
     ```

---

## 4. Componentes e Padrões de Interface

### A. Formulários & Inputs
- Inputs com padding generoso e cantos modernos:
  `w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-colors`
- Mensagens de erro com ícone informativo e tipografia legível (`text-xs text-rose-600 font-medium mt-1.5`).

### B. Tabelas & Listagens de Dados
- Evite tabelas condensadas e sem respiro.
- Use cabeçalhos com texto em caixa alta discreto: `text-xs font-semibold uppercase tracking-wider text-slate-500`
- Linhas com divisórias sutis (`divide-y divide-slate-100 dark:divide-slate-800`) e hover suave (`hover:bg-slate-50/50 dark:hover:bg-slate-850/50`).
- Empty states atraentes com ícone representativo, título encorajador e botão de ação direta quando não houver dados.

### C. Modais e Diálogos de Confirmação
- Fundo escurecido suave com blur: `fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50`
- Caixa do modal com borda arredondada ampla (`rounded-2xl`), sombra profunda (`shadow-2xl`) e animação sutil de entrada.

---

## 5. Responsividade & Mobile-First
- Todo layout deve ser projetado pensando em telas menores e expandido com breakpoints padrão:
  - Mobile: `< 640px` (empilhamento de grids, menus gaveta/collapse, botões com largura total `w-full sm:w-auto`).
  - Tablet: `sm:` e `md:` (grids de 2 colunas, barras laterais colapsáveis).
  - Desktop: `lg:` e `xl:` (layouts estruturados de 3 ou 4 colunas, dashboards informativos).

---

## 6. Checklist de Validação Visual
Antes de finalizar qualquer view ou componente web:
- [ ] O visual causa uma excelente impressão ("wow factor")?
- [ ] Não há nenhum atributo `style="..."` embutido no HTML/Blade?
- [ ] A paleta de cores e o contraste atendem às diretrizes de legibilidade?
- [ ] Todos os estados interativos (`hover`, `focus`, `active`, `disabled`) foram estilizados?
- [ ] O layout foi testado mentalmente ou visualmente em dispositivos móveis?
- [ ] A tipografia possui hierarquia clara sem fontes padrão do navegador?
