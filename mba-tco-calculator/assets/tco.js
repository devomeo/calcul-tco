(function(){
  const opts = window.MBA_TCO_OPTS || { options: {} };
  const defaultOptions = opts.options || {};

  function getElementState(el){
    const props = el.dataset.props ? JSON.parse(el.dataset.props) : {};
    const compare = Math.max(2, Math.min(4, parseInt(props.compare || 2, 10)));
    const vehicles = [];
    for(let i=0;i<compare;i++){
      vehicles.push(createVehicleFromPreset(props.presets && props.presets[i]));
    }
    const state = {
      step: 0,
      compare,
      vehicles,
      fleetEnabled: defaultOptions.interface ? !!defaultOptions.interface.enable_fleet : false,
      fleetCount: defaultOptions.interface ? parseInt(defaultOptions.interface.default_fleet_count || 1,10) : 1,
      results: null,
      loading: false,
      mode: props.mode === 'pro' ? 'pro' : 'simple'
    };

    const queryState = readStateFromQuery();
    if(queryState){
      Object.assign(state, queryState);
    }

    while(state.vehicles.length < state.compare){
      state.vehicles.push(createVehicleFromPreset());
    }
    if(state.vehicles.length > state.compare){
      state.vehicles = state.vehicles.slice(0, state.compare);
    }

    return state;
  }

  function createVehicleFromPreset(presetId){
    const preset = (defaultOptions.vehicles || []).find(v => v.id === presetId);
    const energyDefaults = defaultOptions.energy || {};
    const fiscalDefaults = defaultOptions.fiscalite || {};
    const chargingDefaults = defaultOptions.charging || {};
    const base = {
      id: presetId || '',
      label: preset ? preset.label : '',
      type: preset ? preset.type : 'thermique',
      acquisition: {
        mode: preset ? preset.acquisition_mode : 'achat',
        prix_ttc: preset ? preset.price_ttc : 0,
        valeur_residuelle: preset ? preset.valeur_residuelle : 0,
        loyers_mensuels: preset ? preset.loyer_mensuel : 0,
        frais_entree_sortie: 0,
        entretien_inclus: false
      },
      usage: {
        km_annuel: 20000,
        duree: 4,
        repartition: { urbain: 40, route: 40, autoroute: 20 }
      },
      energie: {
        consommation: {
          urbain: preset ? preset.conso_urbain : 7,
          route: preset ? preset.conso_route : 6,
          autoroute: preset ? preset.conso_autoroute : 7
        },
        prix_carburant: energyDefaults.carburant_eur_l || 0,
        prix_electricite: {
          site: energyDefaults.elec_site || 0,
          home: energyDefaults.elec_home || 0,
          public: energyDefaults.elec_public || 0
        },
        mix_elec: {
          site: energyDefaults.mix_site || 0,
          home: energyDefaults.mix_home || 0,
          public: energyDefaults.mix_public || 0
        },
        coefficient_pertes: energyDefaults.loss_factor || 1.07
      },
      recharge: {
        borne_nb: 0,
        prix_unitaire: chargingDefaults.prix_unitaire_ht || 0,
        maintenance_annuelle: chargingDefaults.maintenance_an || 0,
        subvention_pct: chargingDefaults.subvention_pct || 0,
        ratio_vehicule_borne: chargingDefaults.ratio_vehicule_borne || 1,
        duree_amortissement: chargingDefaults.duree_amortissement || 5
      },
      couts: {
        entretien_an: preset ? preset.entretien_an : 0,
        pneus_an: preset ? preset.pneus_an : 0,
        assurance_an: preset ? preset.assurance_an : 0
      },
      fiscalite: {
        tva_recup: fiscalDefaults.tva_recup || 0,
        bonus_malus: fiscalDefaults.bonus_malus || 0,
        amort_non_deductible: fiscalDefaults.amort_non_deductible || 0,
        divers: 0,
        inclure_aen: !!fiscalDefaults.inclure_aen,
        aen_inclure: false,
        aen_annuel: fiscalDefaults.aen_montant_annuel || 0
      }
    };

    return base;
  }

  function readStateFromQuery(){
    const params = new URLSearchParams(window.location.search);
    const raw = params.get('tco');
    if(!raw){
      return null;
    }
    try{
      const cleaned = raw.replace(/\s/g, '+');
      const json = JSON.parse(base64Decode(cleaned));
      return json;
    }catch(e){
      console.warn('MBA TCO: unable to parse state', e);
      return null;
    }
  }

  function writeStateToQuery(state){
    try{
      const encoded = base64Encode({
        step: state.step,
        compare: state.compare,
        vehicles: state.vehicles,
        fleetEnabled: state.fleetEnabled,
        fleetCount: state.fleetCount,
        mode: state.mode
      });
      const params = new URLSearchParams(window.location.search);
      params.set('tco', encoded);
      const query = params.toString();
      const newUrl = window.location.pathname + (query ? '?' + query : '');
      history.replaceState({}, '', newUrl);
      return window.location.origin + newUrl;
    }catch(e){
      console.warn('MBA TCO: cannot encode state', e);
      return window.location.href;
    }
  }

  function base64Encode(data){
    const json = typeof data === 'string' ? data : JSON.stringify(data);
    const bytes = new TextEncoder().encode(json);
    let binary = '';
    bytes.forEach(byte => {
      binary += String.fromCharCode(byte);
    });
    return btoa(binary);
  }

  function base64Decode(value){
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);
    for(let i=0;i<binary.length;i++){
      bytes[i] = binary.charCodeAt(i);
    }
    return new TextDecoder().decode(bytes);
  }

  function renderAll(){
    document.querySelectorAll('.mba-tco').forEach(renderCalculator);
  }

  const Steps = [
    {
      key: 'type',
      label: opts.i18nType || 'Type de véhicule',
      summary: opts.i18nTypeSummary || 'Sélectionnez la motorisation et, si besoin, un preset pour démarrer plus vite.'
    },
    {
      key: 'acquisition',
      label: opts.i18nAcq || 'Mode d\'acquisition',
      summary: opts.i18nAcqSummary || 'Précisez comment le véhicule est financé afin de calculer loyers, prix et frais associés.'
    },
    {
      key: 'usage',
      label: opts.i18nUsage || 'Usage',
      summary: opts.i18nUsageSummary || 'Renseignez vos kilomètres annuels et la durée d\'utilisation pour mesurer les coûts dans le temps.'
    },
    {
      key: 'energy',
      label: opts.i18nEnergy || 'Énergie',
      summary: opts.i18nEnergySummary || 'Indiquez les consommations et tarifs d\'énergie pour estimer vos dépenses carburant/électricité.'
    },
    {
      key: 'recharge',
      label: opts.i18nRecharge || 'Recharge & bornes',
      summary: opts.i18nRechargeSummary || 'Définissez votre infrastructure de recharge pour intégrer amortissement et maintenance.'
    },
    {
      key: 'costs',
      label: opts.i18nCosts || 'Coûts fixes',
      summary: opts.i18nCostsSummary || 'Complétez les dépenses annuelles récurrentes : entretien, pneus, assurance.'
    },
    {
      key: 'fiscal',
      label: opts.i18nFiscal || 'Fiscalité',
      summary: opts.i18nFiscalSummary || 'Ajustez la fiscalité : TVA récupérable, bonus/malus et charges non déductibles.'
    },
    {
      key: 'review',
      label: opts.i18nReview || 'Résumé',
      summary: opts.i18nReviewSummary || 'Passez en revue vos données avant de lancer le calcul du TCO.'
    }
  ];

  function escapeHTML(str){
    return (str || '').toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function escapeAttr(str){
    return escapeHTML(str)
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function buildTooltip(text){
    if(!text){ return ''; }
    const safe = escapeAttr(text);
    return `<span class="mba-tco__tooltip" tabindex="0" role="note" aria-label="${safe}" data-tooltip="${safe}">?</span>`;
  }

  function buildLabel(text, tooltip){
    const safeText = escapeHTML(text);
    return `<span class="mba-tco__label-text">${safeText}</span>${buildTooltip(tooltip)}`;
  }

  function renderStepHelper(key, fallback){
    const message = opts[key] || fallback;
    if(!message){ return ''; }
    return `<p class="mba-tco__helper">${escapeHTML(message)}</p>`;
  }

  function renderCalculator(el){
    if(!el.__mbaState){
      el.__mbaState = getElementState(el);
    }
    const state = el.__mbaState;

    el.setAttribute('data-loading', state.loading ? 'true' : 'false');
    el.innerHTML = '';

    const progress = document.createElement('div');
    progress.className = 'mba-tco__progress';
    const progressBar = document.createElement('div');
    progressBar.className = 'mba-tco__progress-bar';
    progressBar.style.width = ((state.step + 1) / Steps.length * 100) + '%';
    progress.appendChild(progressBar);
    el.appendChild(progress);

    const stepsEl = document.createElement('div');
    stepsEl.className = 'mba-tco__steps';
    Steps.forEach((step, index) => {
      const stepEl = document.createElement('button');
      stepEl.type = 'button';
      stepEl.className = 'mba-tco__step';
      stepEl.textContent = step.label;
      stepEl.setAttribute('aria-current', state.step === index ? 'step' : 'false');
      stepEl.setAttribute('tabindex', '0');
      stepEl.addEventListener('click', () => {
        state.step = index;
        renderCalculator(el);
      });
      stepsEl.appendChild(stepEl);
    });
    el.appendChild(stepsEl);

    if(state.fleetEnabled){
      const fleetWrapper = document.createElement('div');
      fleetWrapper.className = 'mba-tco__fleet-toggle';
      const label = document.createElement('label');
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = state.fleetCount > 1;
      checkbox.addEventListener('change', () => {
        state.fleetCount = checkbox.checked ? Math.max(2, state.fleetCount || 2) : 1;
        renderCalculator(el);
      });
      label.appendChild(checkbox);
      label.appendChild(document.createTextNode(' ' + (opts.i18nFleet || 'Mode flotte')));
      const fleetInput = document.createElement('input');
      fleetInput.type = 'number';
      fleetInput.min = 1;
      fleetInput.value = state.fleetCount;
      fleetInput.addEventListener('input', e => {
        const value = parseInt(e.target.value || '1', 10);
        state.fleetCount = Math.max(1, value);
      });
      fleetWrapper.appendChild(label);
      fleetWrapper.appendChild(fleetInput);
      el.appendChild(fleetWrapper);
    }

    const stepMeta = Steps[state.step] || {};
    const context = document.createElement('div');
    context.className = 'mba-tco__context';
    context.innerHTML = `
      <div class="mba-tco__context-header">
        <span class="mba-tco__context-step">${state.step + 1}/${Steps.length}</span>
        <div class="mba-tco__context-copy">
          <h2>${escapeHTML(stepMeta.label || '')}</h2>
          <p>${escapeHTML(stepMeta.summary || '')}</p>
        </div>
      </div>
    `;
    el.appendChild(context);

    const cards = document.createElement('div');
    cards.className = 'mba-tco__cards';

    for(let index=0; index<state.compare; index++){
      const vehicle = state.vehicles[index] || createVehicleFromPreset();
      state.vehicles[index] = vehicle;
      const card = document.createElement('article');
      card.className = 'mba-tco__card';
      card.setAttribute('role', 'group');
      card.setAttribute('aria-label', (vehicle.label || 'Véhicule ' + (index+1)));
      card.tabIndex = 0;
      card.innerHTML = renderCardContent(vehicle, index, state);
      card.addEventListener('keydown', ev => {
        if(ev.key === 'Enter' || ev.key === ' '){
          ev.preventDefault();
          focusFirstInput(card);
        }
      });
      cards.appendChild(card);
    }

    el.appendChild(cards);

    const actions = document.createElement('div');
    actions.className = 'mba-tco__actions';

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'mba-tco__button mba-tco__button--ghost';
    prevBtn.textContent = opts.i18nPrev || 'Précédent';
    prevBtn.disabled = state.step === 0;
    prevBtn.addEventListener('click', () => {
      state.step = Math.max(0, state.step - 1);
      renderCalculator(el);
    });

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'mba-tco__button';
    nextBtn.textContent = state.step === Steps.length - 1 ? (opts.i18nCalculate || 'Calculer') : (opts.i18nNext || 'Suivant');
    nextBtn.addEventListener('click', () => {
      if(state.step === Steps.length - 1){
        calculate(el);
      }else{
        state.step = Math.min(Steps.length - 1, state.step + 1);
        if(window.dataLayer){ window.dataLayer.push({ event: 'tco_step', step: state.step }); }
        renderCalculator(el);
      }
    });

    actions.appendChild(prevBtn);
    actions.appendChild(nextBtn);
    el.appendChild(actions);

    if(state.results){
      el.appendChild(renderResults(state, el));
    }
  }

  function focusFirstInput(card){
    const input = card.querySelector('input, select');
    if(input){ input.focus(); }
  }

  function renderCardContent(vehicle, index, state){
    const stepKey = Steps[state.step].key;
    switch(stepKey){
      case 'type':
        return renderTypeStep(vehicle, index, state);
      case 'acquisition':
        return renderAcquisitionStep(vehicle, index);
      case 'usage':
        return renderUsageStep(vehicle);
      case 'energy':
        return renderEnergyStep(vehicle);
      case 'recharge':
        return renderRechargeStep(vehicle);
      case 'costs':
        return renderCostsStep(vehicle);
      case 'fiscal':
        return renderFiscalStep(vehicle);
      case 'review':
      default:
        return renderReviewStep(vehicle, state);
    }
  }

  function renderTypeStep(vehicle, index, state){
    const types = [
      { value: 'thermique', label: 'Thermique' },
      { value: 'hybride', label: 'Hybride' },
      { value: 'phev', label: 'PHEV' },
      { value: 'bev', label: 'Électrique' }
    ];
    const mode = vehicle.acquisition.mode || 'achat';
    const header = `<header><h3>${vehicle.label || ('Véhicule ' + (index+1))}</h3><p>${opts.i18nMode || 'Mode'}: ${mode.toUpperCase()}</p></header>`;
    const buttons = types.map(type => {
      const active = vehicle.type === type.value;
      return `<button type="button" class="mba-tco__button ${active ? '' : 'mba-tco__button--ghost'}" data-action="set-type" data-value="${type.value}" aria-pressed="${active}">${type.label}</button>`;
    }).join('');
    const presetSelect = renderPresetSelect(vehicle, index, state);
    const helper = renderStepHelper('i18nTypeHelper', opts.i18nChooseType || 'Choisissez le type de motorisation.');
    return header + presetSelect + helper + `<div class="mba-tco__range-group">${buttons}</div>`;
  }

  function renderPresetSelect(vehicle, index, state){
    const list = defaultOptions.vehicles || [];
    if(!list.length){
      return '';
    }
    const options = ['<option value="">' + (opts.i18nChoosePreset || 'Choisir un preset') + '</option>'];
    list.forEach(item => {
      options.push(`<option value="${item.id}" ${vehicle.id === item.id ? 'selected' : ''}>${item.label}</option>`);
    });
    return `<div class="mba-tco__field"><label class="mba-tco__label" for="mba-tco-preset-${index}">${buildLabel(opts.i18nPreset || 'Preset')}</label><select id="mba-tco-preset-${index}" data-action="preset" data-index="${index}">${options.join('')}</select></div>`;
  }

  function renderAcquisitionStep(vehicle){
    const acq = vehicle.acquisition;
    const helper = renderStepHelper('i18nAcqHelper', 'Sélectionnez achat, LLD ou LOA et renseignez les montants associés.');
    return `
      <header><h3>${vehicle.label || opts.i18nVehicle || 'Véhicule'}</h3></header>
      ${helper}
      <div class="mba-tco__field">
        <label class="mba-tco__label">${buildLabel(opts.i18nAcqMode || 'Mode d\'acquisition')}</label>
        <select data-action="acq-mode">
          <option value="achat" ${acq.mode === 'achat' ? 'selected' : ''}>${opts.i18nBuy || 'Achat'}</option>
          <option value="lld" ${acq.mode === 'lld' ? 'selected' : ''}>LLD</option>
          <option value="loa" ${acq.mode === 'loa' ? 'selected' : ''}>LOA</option>
        </select>
      </div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nPrice || 'Prix TTC')}</label><input type="number" data-action="acq-price" value="${acq.prix_ttc || 0}" step="100"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nVR || 'Valeur résiduelle', opts.tipResidual || 'Montant estimé de revente en fin de période.')}</label><input type="number" data-action="acq-vr" value="${acq.valeur_residuelle || 0}" step="100"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nRent || 'Loyer mensuel', opts.tipRent || 'Mensualité contractuelle hors services inclus.')}</label><input type="number" data-action="acq-rent" value="${acq.loyers_mensuels || 0}" step="10"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nFees || 'Frais entrée/sortie', opts.tipFees || 'Frais de mise en service ou de restitution liés au contrat.')}</label><input type="number" data-action="acq-fees" value="${acq.frais_entree_sortie || 0}" step="10"></div>
      <div class="mba-tco__field"><label><input type="checkbox" data-action="acq-maint" ${acq.entretien_inclus ? 'checked' : ''}> ${opts.i18nMaintenance || 'Entretien inclus dans le contrat'}</label></div>
    `;
  }

  function renderUsageStep(vehicle){
    const usage = vehicle.usage;
    const helper = renderStepHelper('i18nUsageHelper', 'Renseignez la durée d\'utilisation et la répartition des parcours.');
    return `
      <header><h3>${vehicle.label || 'Véhicule'}</h3></header>
      ${helper}
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nKm || 'Kilométrage annuel')}</label><input type="number" data-action="usage-km" value="${usage.km_annuel}" step="1000"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nDuration || 'Durée (ans)')}</label><input type="number" min="2" max="6" data-action="usage-duration" value="${usage.duree}" step="1"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nMix || 'Répartition Urbain / Route / Autoroute (%)', opts.tipUsageMix || 'Ajustez la part de chaque type de trajet, le total sera recalculé à 100 %.')}</label>
        <div class="mba-tco__range-group">
          ${renderMixInput('urbain', usage.repartition.urbain)}
          ${renderMixInput('route', usage.repartition.route)}
          ${renderMixInput('autoroute', usage.repartition.autoroute)}
        </div>
      </div>
    `;
  }

  function renderMixInput(key, value){
    return `<label>${key.charAt(0).toUpperCase() + key.slice(1)} <input type="number" min="0" max="100" data-action="mix-${key}" value="${value}" step="1"></label>`;
  }

  function renderEnergyStep(vehicle){
    const e = vehicle.energie;
    const helper = renderStepHelper('i18nEnergyHelper', 'Indiquez les consommations et prix de l\'énergie selon vos sites de recharge.');
    return `
      <header><h3>${vehicle.label || 'Véhicule'}</h3></header>
      ${helper}
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nConso || 'Consommation (urbain L/100 ou kWh/100)')}</label>
        <div class="mba-tco__range-group">
          ${renderEnergyInput('urbain', e.consommation.urbain)}
          ${renderEnergyInput('route', e.consommation.route)}
          ${renderEnergyInput('autoroute', e.consommation.autoroute)}
        </div>
      </div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nFuel || 'Prix carburant €/L')}</label><input type="number" data-action="prix-carburant" value="${e.prix_carburant}" step="0.01"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nElec || 'Prix électricité €/kWh')}</label>
        <div class="mba-tco__range-group">
          ${renderElecInput('site', e.prix_electricite.site)}
          ${renderElecInput('home', e.prix_electricite.home)}
          ${renderElecInput('public', e.prix_electricite.public)}
        </div>
      </div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nElecMix || 'Mix recharge %', opts.tipElecMix || 'Définissez la part de recharge sur site, à domicile et en public. Le total sera équilibré à 100 %.')}</label>
        <div class="mba-tco__range-group">
          ${renderMixElec('site', e.mix_elec.site)}
          ${renderMixElec('home', e.mix_elec.home)}
          ${renderMixElec('public', e.mix_elec.public)}
        </div>
      </div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nLoss || 'Coefficient pertes élec', opts.tipLoss || 'Prend en compte les pertes de charge (kWh consommés vs délivrés).')}</label><input type="number" step="0.01" data-action="loss" value="${e.coefficient_pertes}"></div>
    `;
  }

  function renderEnergyInput(key, value){
    return `<label>${key} <input type="number" step="0.1" data-action="conso-${key}" value="${value}"></label>`;
  }
  function renderElecInput(key, value){
    return `<label>${key} <input type="number" step="0.01" data-action="elec-${key}" value="${value}"></label>`;
  }
  function renderMixElec(key, value){
    return `<label>${key} <input type="number" min="0" max="100" data-action="mix-elec-${key}" value="${value}"></label>`;
  }

  function renderRechargeStep(vehicle){
    const r = vehicle.recharge;
    if(vehicle.type !== 'bev' && vehicle.type !== 'phev'){
      return `<header><h3>${vehicle.label || 'Véhicule'}</h3></header><p class="mba-tco__helper">${opts.i18nNoCharger || 'Pas de bornes nécessaires pour ce véhicule.'}</p>`;
    }
    const helper = renderStepHelper('i18nRechargeHelper', 'Renseignez les investissements et la maintenance liés aux bornes.');
    return `
      <header><h3>${vehicle.label || 'Véhicule'}</h3></header>
      ${helper}
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nChargerCount || 'Nombre de bornes')}</label><input type="number" data-action="borne-nb" value="${r.borne_nb}" step="1"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nChargerCapex || 'CAPEX borne HT', opts.tipChargerCapex || 'Investissement unitaire hors taxes pour l\'installation d\'une borne.')}</label><input type="number" data-action="borne-capex" value="${r.prix_unitaire}" step="10"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nChargerMaint || 'Maintenance annuelle', opts.tipChargerMaint || 'Coût annuel de maintenance, supervision et SAV de la borne.')}</label><input type="number" data-action="borne-maint" value="${r.maintenance_annuelle}" step="10"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nChargerSub || 'Subvention Advenir %', opts.tipChargerSub || 'Pourcentage de subvention Advenir déduit du CAPEX admissible.')}</label><input type="number" data-action="borne-sub" value="${r.subvention_pct}" step="1"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nChargerRatio || 'Ratio véhicules / borne', opts.tipChargerRatio || 'Nombre de véhicules desservis par une borne pour répartir les coûts.')}</label><input type="number" data-action="borne-ratio" value="${r.ratio_vehicule_borne}" step="1"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nChargerAmort || 'Durée amortissement (ans)', opts.tipChargerAmort || 'Nombre d\'années utilisées pour amortir l\'investissement initial.')}</label><input type="number" data-action="borne-amort" value="${r.duree_amortissement}" step="1"></div>
    `;
  }

  function renderCostsStep(vehicle){
    const c = vehicle.couts;
    const helper = renderStepHelper('i18nCostsHelper', 'Complétez les charges annuelles si elles ne sont pas déjà incluses ailleurs.');
    return `
      <header><h3>${vehicle.label || 'Véhicule'}</h3></header>
      ${helper}
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nMaintenanceYear || 'Entretien annuel')}</label><input type="number" data-action="cout-entretien" value="${c.entretien_an}" step="10"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nTyres || 'Pneus annuel')}</label><input type="number" data-action="cout-pneus" value="${c.pneus_an}" step="10"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nInsurance || 'Assurance annuelle')}</label><input type="number" data-action="cout-assurance" value="${c.assurance_an}" step="10"></div>
    `;
  }

  function renderFiscalStep(vehicle){
    const f = vehicle.fiscalite;
    const helper = renderStepHelper('i18nFiscalHelper', 'Calibrez la fiscalité applicable à ce véhicule et les montants spécifiques.');
    return `
      <header><h3>${vehicle.label || 'Véhicule'}</h3></header>
      ${helper}
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nTVA || 'TVA récupérable %', opts.tipTVA || 'Pourcentage de TVA pouvant être récupéré sur le véhicule ou les loyers.')}</label><input type="number" data-action="fisc-tva" value="${f.tva_recup}" step="1"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nBonus || 'Bonus / Malus', opts.tipBonus || 'Montant unique positif (bonus) ou négatif (malus) appliqué à l\'achat.')}</label><input type="number" data-action="fisc-bonus" value="${f.bonus_malus}" step="10"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nAmort || 'Amortissements non déductibles', opts.tipAmort || 'Part des amortissements qui ne peut pas être déduite fiscalement sur la durée.')}</label><input type="number" data-action="fisc-amort" value="${f.amort_non_deductible}" step="10"></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nOther || 'Autres charges fiscales', opts.tipFiscOther || 'Taxe annuelle, TVS ou toute autre charge non incluse ailleurs.')}</label><input type="number" data-action="fisc-divers" value="${f.divers || 0}" step="10"></div>
      <div class="mba-tco__field"><label><input type="checkbox" data-action="fisc-aen-toggle" ${f.aen_inclure ? 'checked' : ''}> ${opts.i18nAenToggle || 'Inclure AEN dans le calcul'}</label></div>
      <div class="mba-tco__field"><label class="mba-tco__label">${buildLabel(opts.i18nAenAmount || 'Montant AEN annuel', opts.tipAENAmount || 'Montant annuel d\'Aide à l\'Électrification des flottes à inclure si activé.')}</label><input type="number" data-action="fisc-aen" value="${f.aen_annuel}" step="10"></div>
    `;
  }

  function renderReviewStep(vehicle, state){
    const helper = renderStepHelper('i18nReviewHelper', 'Validez les données clés avant de lancer le calcul.');
    return `
      <header><h3>${vehicle.label || 'Véhicule'}</h3></header>
      ${helper || `<p class="mba-tco__helper">${opts.i18nReviewText || 'Vérifiez vos informations avant de lancer le calcul.'}</p>`}
      <ul>
        <li>${vehicle.type.toUpperCase()} – ${vehicle.acquisition.mode.toUpperCase()}</li>
        <li>${vehicle.usage.km_annuel} km/an · ${vehicle.usage.duree} ans</li>
        <li>${opts.i18nFleetCount || 'Nombre de véhicules'}: ${state.fleetCount}</li>
      </ul>
    `;
  }

  function calculate(el){
    const state = el.__mbaState;
    state.loading = true;
    renderCalculator(el);

    const body = {
      vehicles: state.vehicles,
      fleet_count: state.fleetCount
    };

    fetch(opts.rest.url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': opts.rest.nonce
      },
      body: JSON.stringify(body)
    }).then(response => {
      if(!response.ok){ throw new Error('Network error'); }
      return response.json();
    }).then(data => {
      state.results = data;
      state.loading = false;
      renderCalculator(el);
      if(window.dataLayer){ window.dataLayer.push({ event: 'tco_calculated' }); }
    }).catch(error => {
      state.loading = false;
      state.results = { error: error.message };
      renderCalculator(el);
    });
  }

  function renderResults(state, containerEl){
    const container = document.createElement('section');
    container.className = 'mba-tco__results';
    container.setAttribute('role', 'region');
    container.innerHTML = '<h2>' + (opts.i18nResults || 'Résultats du calcul') + '</h2>';

    if(state.results && state.results.error){
      container.innerHTML += '<p class="mba-tco__status" role="status">' + state.results.error + '</p>';
      return container;
    }

    const vehicles = state.results ? state.results.vehicles || [] : [];
    if(!vehicles.length){
      container.innerHTML += '<p class="mba-tco__status">' + (opts.i18nNoResults || 'Aucun résultat disponible.') + '</p>';
      return container;
    }

    const tableWrapper = document.createElement('div');
    tableWrapper.className = 'mba-tco__table-wrapper';
    const table = document.createElement('table');
    table.className = 'mba-tco__results-table';
    const head = document.createElement('thead');
    const headRow = document.createElement('tr');
    headRow.innerHTML = '<th>' + (opts.i18nVehicle || 'Véhicule') + '</th><th>Total</th><th>€/mois</th><th>€/km</th>';
    head.appendChild(headRow);
    table.appendChild(head);

    const body = document.createElement('tbody');
    vehicles.forEach(v => {
      const row = document.createElement('tr');
      const badge = v.meta.is_best ? `<span class="mba-tco__badge">${opts.i18nBest || 'Meilleur TCO'}</span>` : '';
      row.innerHTML = `<td>${v.meta.label || ''} ${badge}</td><td>${formatCurrency(v.totals.tco_total)} €</td><td>${formatCurrency(v.totals.tco_monthly)} €</td><td>${formatCurrency(v.totals.tco_km)} €/km</td>`;
      body.appendChild(row);
    });
    table.appendChild(body);
    tableWrapper.appendChild(table);
    container.appendChild(tableWrapper);

    container.appendChild(renderDetailTable(vehicles));
    container.appendChild(renderResultActions(state, containerEl));
    return container;
  }

  function renderDetailTable(vehicles){
    const categories = [
      { key: 'acquisition', label: 'Acquisition / Loyers' },
      { key: 'energie', label: 'Énergie' },
      { key: 'entretien', label: 'Entretien' },
      { key: 'pneus', label: 'Pneus' },
      { key: 'assurance', label: 'Assurance' },
      { key: 'fiscalite', label: 'Fiscalité' },
      { key: 'bornes', label: 'Bornes amorties + maintenance' }
    ];
    const wrapper = document.createElement('div');
    wrapper.className = 'mba-tco__table-wrapper';
    const table = document.createElement('table');
    table.className = 'mba-tco__results-table';
    const head = document.createElement('thead');
    const headRow = document.createElement('tr');
    headRow.innerHTML = '<th>' + (opts.i18nPost || 'Poste') + '</th>' + vehicles.map(v => `<th>${v.meta.label || ''}</th>`).join('');
    head.appendChild(headRow);
    table.appendChild(head);
    const body = document.createElement('tbody');
    categories.forEach(cat => {
      const row = document.createElement('tr');
      row.innerHTML = '<td>' + cat.label + '</td>' + vehicles.map(v => {
        let value = v.detail[cat.key] || 0;
        if(cat.key === 'acquisition'){
          value = (v.detail.acquisition || 0) + (v.detail.loyers || 0);
        }
        return `<td>${formatCurrency(value)} €</td>`;
      }).join('');
      body.appendChild(row);
    });
    table.appendChild(body);
    wrapper.appendChild(table);
    return wrapper;
  }

  function renderResultActions(state, el){
    const container = document.createElement('div');
    container.className = 'mba-tco__actions';

    const copyButton = document.createElement('button');
    copyButton.type = 'button';
    copyButton.className = 'mba-tco__button';
    copyButton.textContent = opts.i18nCopy || 'Copier le résumé';
    copyButton.addEventListener('click', () => {
      const summary = buildSummary(state);
      if(navigator.clipboard){
        navigator.clipboard.writeText(summary).then(() => {
          copyButton.textContent = opts.i18nCopied || 'Résumé copié !';
          setTimeout(() => copyButton.textContent = opts.i18nCopy || 'Copier le résumé', 2000);
        });
      }
    });

    const shareButton = document.createElement('button');
    shareButton.type = 'button';
    shareButton.className = 'mba-tco__button mba-tco__button--ghost';
    shareButton.textContent = opts.i18nShare || 'Partager ce calcul';
    shareButton.addEventListener('click', () => {
      const url = writeStateToQuery(state);
      if(navigator.clipboard){
        navigator.clipboard.writeText(url).then(() => {
          shareButton.textContent = opts.i18nLinkCopied || 'Lien copié';
          setTimeout(() => shareButton.textContent = opts.i18nShare || 'Partager ce calcul', 2000);
        });
      }
      if(window.dataLayer){ window.dataLayer.push({ event: 'tco_share' }); }
    });

    const resetButton = document.createElement('button');
    resetButton.type = 'button';
    resetButton.className = 'mba-tco__button mba-tco__button--ghost';
    resetButton.textContent = opts.i18nReset || 'Recommencer';
    resetButton.addEventListener('click', () => {
      const params = new URLSearchParams(window.location.search);
      params.delete('tco');
      const query = params.toString();
      history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));
      el.__mbaState = getElementState(el);
      renderCalculator(el);
    });

    container.appendChild(copyButton);
    container.appendChild(shareButton);
    container.appendChild(resetButton);
    return container;
  }

  function buildSummary(state){
    if(!state.results || !state.results.vehicles){
      return '';
    }
    const lines = [];
    lines.push('Calculateur TCO – Résumé');
    state.results.vehicles.forEach((v, index) => {
      lines.push(`#${index+1} ${v.meta.label || 'Véhicule'} : ${formatCurrency(v.totals.tco_total)} € (mensuel ${formatCurrency(v.totals.tco_monthly)} € / km ${formatCurrency(v.totals.tco_km)} €/km)`);
    });
    if(state.results.vehicles.length > 1){
      const best = state.results.vehicles[0];
      const second = state.results.vehicles[1];
      if(second){
        const diff = Math.abs((second.totals.tco_total || 0) - (best.totals.tco_total || 0));
        lines.push('Économie vs ' + (second.meta.label || 'suivant') + ' : ' + formatCurrency(diff) + ' €');
      }
    }
    return lines.join('\n');
  }

  function formatCurrency(value){
    return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value || 0);
  }

  document.addEventListener('input', function(e){
    const card = e.target.closest('.mba-tco__card');
    if(!card){ return; }
    const container = card.closest('.mba-tco');
    if(!container || !container.__mbaState){ return; }
    const state = container.__mbaState;
    const index = Array.prototype.indexOf.call(container.querySelectorAll('.mba-tco__card'), card);
    const vehicle = state.vehicles[index];
    if(!vehicle){ return; }
    const action = e.target.dataset.action;
    if(!action){ return; }

    const value = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
    switch(action){
      case 'acq-mode': vehicle.acquisition.mode = value; break;
      case 'acq-price': vehicle.acquisition.prix_ttc = parseFloat(value || 0); break;
      case 'acq-vr': vehicle.acquisition.valeur_residuelle = parseFloat(value || 0); break;
      case 'acq-rent': vehicle.acquisition.loyers_mensuels = parseFloat(value || 0); break;
      case 'acq-fees': vehicle.acquisition.frais_entree_sortie = parseFloat(value || 0); break;
      case 'acq-maint': vehicle.acquisition.entretien_inclus = !!value; break;
      case 'usage-km': vehicle.usage.km_annuel = parseFloat(value || 0); break;
      case 'usage-duration': vehicle.usage.duree = parseInt(value || 0, 10); break;
      case 'mix-urbain': vehicle.usage.repartition.urbain = clampPercent(value); normalizeMix(vehicle.usage.repartition); break;
      case 'mix-route': vehicle.usage.repartition.route = clampPercent(value); normalizeMix(vehicle.usage.repartition); break;
      case 'mix-autoroute': vehicle.usage.repartition.autoroute = clampPercent(value); normalizeMix(vehicle.usage.repartition); break;
      case 'conso-urbain': vehicle.energie.consommation.urbain = parseFloat(value || 0); break;
      case 'conso-route': vehicle.energie.consommation.route = parseFloat(value || 0); break;
      case 'conso-autoroute': vehicle.energie.consommation.autoroute = parseFloat(value || 0); break;
      case 'prix-carburant': vehicle.energie.prix_carburant = parseFloat(value || 0); break;
      case 'elec-site': vehicle.energie.prix_electricite.site = parseFloat(value || 0); break;
      case 'elec-home': vehicle.energie.prix_electricite.home = parseFloat(value || 0); break;
      case 'elec-public': vehicle.energie.prix_electricite.public = parseFloat(value || 0); break;
      case 'mix-elec-site': vehicle.energie.mix_elec.site = clampPercent(value); normalizeMix(vehicle.energie.mix_elec); break;
      case 'mix-elec-home': vehicle.energie.mix_elec.home = clampPercent(value); normalizeMix(vehicle.energie.mix_elec); break;
      case 'mix-elec-public': vehicle.energie.mix_elec.public = clampPercent(value); normalizeMix(vehicle.energie.mix_elec); break;
      case 'loss': vehicle.energie.coefficient_pertes = parseFloat(value || 1); break;
      case 'borne-nb': vehicle.recharge.borne_nb = parseFloat(value || 0); break;
      case 'borne-capex': vehicle.recharge.prix_unitaire = parseFloat(value || 0); break;
      case 'borne-maint': vehicle.recharge.maintenance_annuelle = parseFloat(value || 0); break;
      case 'borne-sub': vehicle.recharge.subvention_pct = clampPercent(value); break;
      case 'borne-ratio': vehicle.recharge.ratio_vehicule_borne = Math.max(1, parseFloat(value || 1)); break;
      case 'borne-amort': vehicle.recharge.duree_amortissement = Math.max(1, parseFloat(value || 1)); break;
      case 'cout-entretien': vehicle.couts.entretien_an = parseFloat(value || 0); break;
      case 'cout-pneus': vehicle.couts.pneus_an = parseFloat(value || 0); break;
      case 'cout-assurance': vehicle.couts.assurance_an = parseFloat(value || 0); break;
      case 'fisc-tva': vehicle.fiscalite.tva_recup = clampPercent(value); break;
      case 'fisc-bonus': vehicle.fiscalite.bonus_malus = parseFloat(value || 0); break;
      case 'fisc-amort': vehicle.fiscalite.amort_non_deductible = parseFloat(value || 0); break;
      case 'fisc-divers': vehicle.fiscalite.divers = parseFloat(value || 0); break;
      case 'fisc-aen-toggle': vehicle.fiscalite.aen_inclure = !!value; break;
      case 'fisc-aen': vehicle.fiscalite.aen_annuel = parseFloat(value || 0); break;
      default: break;
    }
    state.results = null;
  });

  document.addEventListener('click', function(e){
    const btn = e.target.closest('button[data-action]');
    if(!btn){
      const preset = e.target.matches('select[data-action="preset"]') ? e.target : null;
      if(preset){ handlePresetChange(preset); }
      return;
    }
    const card = btn.closest('.mba-tco__card');
    const container = card ? card.closest('.mba-tco') : null;
    if(!container || !container.__mbaState){ return; }
    const state = container.__mbaState;
    const index = Array.prototype.indexOf.call(container.querySelectorAll('.mba-tco__card'), card);
    const vehicle = state.vehicles[index];
    if(btn.dataset.action === 'set-type'){
      vehicle.type = btn.dataset.value;
      renderCalculator(container);
    }
  });

  document.addEventListener('change', function(e){
    if(e.target.matches('select[data-action="preset"]')){
      handlePresetChange(e.target);
    }
  });

  function handlePresetChange(select){
    const container = select.closest('.mba-tco');
    if(!container || !container.__mbaState){ return; }
    const state = container.__mbaState;
    const index = parseInt(select.dataset.index, 10);
    const presetId = select.value;
    state.vehicles[index] = createVehicleFromPreset(presetId);
    renderCalculator(container);
  }

  function clampPercent(value){
    const num = parseFloat(value || 0);
    if(num < 0){ return 0; }
    if(num > 100){ return 100; }
    return num;
  }

  function normalizeMix(mix){
    const total = (mix.urbain || 0) + (mix.route || 0) + (mix.autoroute || 0);
    if(total === 0){ return; }
    mix.urbain = parseFloat(((mix.urbain / total) * 100).toFixed(2));
    mix.route = parseFloat(((mix.route / total) * 100).toFixed(2));
    mix.autoroute = parseFloat(((mix.autoroute / total) * 100).toFixed(2));
  }

  renderAll();
})();
