const dataUrl = (action, params = {}) => `api.php?action=${encodeURIComponent(action)}&${new URLSearchParams(params)}`;
const byId = id => document.getElementById(id);
const savedReport = window.imSafeReportValues || {};

const profile = {
  Green: { color: 'green', hint: 'Green protocol applied: stable selections are prefilled and no immediate assistance is requested.', impacts: ['No observed impact', 'Minor disruption resolved', 'Routine community check-in'], situations: ['Conditions stable — no active threat', 'Area safe for normal movement', 'Monitoring only'], needs: ['No immediate assistance required'], defaults: { impact: 'No observed impact', situation: 'Conditions stable — no active threat', description: 'Routine rapid assessment: no immediate danger or assistance requirement reported at the selected location.' } },
  Orange: { color: 'orange', hint: 'Orange protocol applied: document areas of concern and preparedness needs.', impacts: ['Moderate community disruption', 'Partial access limitation', 'Services disrupted', 'Localized households affected'], situations: ['Hazard conditions increasing', 'Access is limited in parts of the area', 'Community is preparing to evacuate', 'Response team assessment needed'], needs: ['Food & water', 'Medical assistance', 'Temporary shelter', 'Communication support', 'Road clearing'], defaults: { impact: '', situation: '', description: '' } },
  Red: { color: 'red', hint: 'Red protocol applied: capture urgent life-safety and response requirements.', impacts: ['Critical life-safety risk', 'Widespread household impact', 'Evacuation required', 'Major infrastructure disruption'], situations: ['People are in immediate danger', 'Evacuation is actively required', 'Road access is blocked', 'Rescue resources are required'], needs: ['Rescue team', 'Medical assistance', 'Evacuation transport', 'Food & water', 'Emergency shelter', 'Power and communications'], defaults: { impact: '', situation: '', description: '' } },
};
const particulars = {
  Flood: ['Flash flood', 'River flooding', 'Coastal flooding', 'Urban or street flooding', 'Dam or levee-related flooding'],
  'Tropical Cyclone': ['Strong winds', 'Storm surge', 'Heavy rainfall', 'Rain-induced flooding'],
  Landslide: ['Rain-induced landslide', 'Rockfall', 'Mudflow or debris flow', 'Slope collapse', 'Earthquake-induced landslide'],
  Thunderstorm: ['Lightning', 'Severe wind gusts', 'Hail', 'Intense rainfall'],
  Earthquake: ['Ground shaking', 'Building damage or collapse', 'Ground rupture', 'Liquefaction', 'Aftershock impacts'],
  'Volcanic Eruption': ['Ashfall', 'Lava flow', 'Pyroclastic flow', 'Lahar', 'Volcanic gas'],
  Tsunami: ['Coastal inundation', 'Rapid sea-level change', 'Strong coastal currents', 'Wave damage'],
  Fire: ['Residential fire', 'Commercial fire', 'Electrical fire', 'Industrial fire', 'Vehicle fire'],
  Wildfire: ['Forest fire', 'Grass fire', 'Brush fire'],
  'Hazardous Material Incident': ['Chemical spill', 'Gas leak', 'Fuel spill', 'Unknown hazardous substance'],
};

const form = byId('rapidForm');
if (form) {
  const specific = byId('specificType');
  const particularType = byId('particularType');
  const rapid = byId('rapidSection');
  const flood = byId('floodSection');
  const selects = ['region', 'province', 'municipality', 'barangay'].map(byId);
  const names = ['regionName', 'provinceName', 'municipalityName', 'barangayName'].map(byId);
  const actions = ['regions', 'provinces', 'municipalities', 'barangays'];
  const prompts = ['Select region', 'Select province', 'Select city / municipality', 'Select barangay'];
  const parentPrompts = ['Loading regions…', 'Select region first', 'Select province first', 'Select city / municipality first'];
  const errorBox = byId('locationError');
  const meta = byId('locationMeta');
  const retry = byId('retryLocations');
  const submit = form.querySelector('button[type="submit"]');
  const evidence = byId('evidence');
  const evidenceCamera = byId('evidenceCamera');
  const launchCamera = byId('launchCamera');
  const cameraDialog = byId('cameraDialog');
  const cameraVideo = byId('cameraVideo');
  const cameraCanvas = byId('cameraCanvas');
  const cameraStatus = byId('cameraStatus');
  const capturePhoto = byId('capturePhoto');
  const closeCamera = byId('closeCamera');
  const cancelCamera = byId('cancelCamera');
  const evidenceDrop = evidence.closest('.evidence-drop');
  const evidenceStatus = byId('evidenceStatus');
  const coordinateStatus = byId('coordinateStatus');
  const latitude = byId('photoLatitude');
  const longitude = byId('photoLongitude');
  const retryCoordinates = byId('retryCoordinates');
  const evidencePreview = byId('evidencePreview');
  const evidenceCoordinatesOverlay = byId('evidenceCoordinatesOverlay');
  const evidencePreviewImage = document.createElement('img');
  evidencePreviewImage.id = 'evidencePreviewImage';
  evidencePreview.prepend(evidencePreviewImage);
  let evidencePreviewUrl = '';
  let cameraStream = null;
  const coordinateHelp = 'Coordinates are optional. After selecting a photo, allow location access to attach the device position.';
  const requests = new Map();
  let revision = 0;
  let failedLevel = null;
  let legendDraft = { impact: '', situation: '', description: '', needs: [] };

  const fill = (select, values, prompt) => {
    select.replaceChildren(new Option(prompt, ''));
    values.forEach(value => select.add(new Option(value.name ?? value, value.code ?? value)));
  };
  const choose = (select, value) => {
    if (typeof value === 'string' && [...select.options].some(option => option.value === value)) select.value = value;
  };
  const syncName = index => {
    names[index].value = selects[index].value ? selects[index].selectedOptions[0].textContent : '';
  };
  const readyText = () => {
    const missing = [];
    if (!form.querySelector('[name="legend"]:checked')) missing.push('community status');
    if (!specific.value || !particularType.value) missing.push('hazard');
    const locationReady = selects.every(select => !select.disabled && select.value);
    if (!locationReady) missing.push('exact location');
    submit.disabled = requests.size > 0 || !locationReady;
    byId('readyText').textContent = requests.size ? 'Loading location choices. You can keep filling in the other sections.'
      : missing.length ? 'Select ' + missing.join(', ') + ' to continue.'
      : form.querySelector(':invalid') ? 'Complete the required assessment fields before submitting.'
      : 'Your required details are complete. Review your answers, then submit.';
  };
  const updateFlood = () => {
    const active = specific.value === 'Flood';
    flood.hidden = !active;
    flood.querySelectorAll('input, select').forEach(input => { input.disabled = !active; });
    ['waterLevel', 'waterTrend', 'roadPassability'].forEach(id => { byId(id).required = active; });
    readyText();
  };
  const setLegend = (legend, restoring = false) => {
    const current = profile[legend];
    if (!current) return;
    if (!byId('reporterDescription').disabled && !restoring) {
      legendDraft = {
        impact: byId('impactDetail').value,
        situation: byId('currentSituation').value,
        description: byId('reporterDescription').value,
        needs: [...form.querySelectorAll('[name="needs[]"]:checked')].map(input => input.value)
      };
    }
    const values = restoring ? {
      impact: savedReport.impact_detail, situation: savedReport.current_situation,
      description: savedReport.reporter_description,
      needs: Array.isArray(savedReport.needs) ? savedReport.needs : []
    } : legendDraft;
    rapid.hidden = false;
    byId('legendHint').textContent = current.hint;
    byId('legendHint').className = 'legend-hint ' + current.color;
    byId('protocolLabel').textContent = 'Choices are configured for the ' + legend + ' protocol.';
    byId('assessmentColor').replaceChildren(new Option(legend.toUpperCase()));
    fill(byId('impactDetail'), current.impacts, 'Select observed impact');
    fill(byId('currentSituation'), current.situations, 'Select current situation');
    choose(byId('impactDetail'), legend === 'Green' ? current.defaults.impact : values.impact);
    choose(byId('currentSituation'), legend === 'Green' ? current.defaults.situation : values.situation);
    byId('reporterDescription').value = legend === 'Green' ? current.defaults.description : (typeof values.description === 'string' ? values.description : '');
    ['impactDetail', 'currentSituation', 'reporterDescription'].forEach(id => { byId(id).disabled = legend === 'Green'; });
    byId('needsList').replaceChildren(...current.needs.map(need => {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.type = 'checkbox'; input.name = 'needs[]'; input.value = need;
      input.checked = legend === 'Green' || values.needs.includes(need);
      input.disabled = legend === 'Green';
      label.append(input, document.createTextNode(need));
      return label;
    }));
    readyText();
  };

  const resetFrom = level => {
    for (let index = level; index < selects.length; index++) {
      requests.get(index)?.abort();
      requests.delete(index);
      selects[index].replaceChildren(new Option(parentPrompts[index], ''));
      selects[index].disabled = true;
      selects[index].removeAttribute('aria-invalid');
      selects[index].removeAttribute('aria-busy');
      names[index].value = '';
    }
    errorBox.hidden = true; retry.hidden = true; failedLevel = null;
    readyText();
  };
  const paramsFor = level => level === 1 ? { region: selects[0].value }
    : level === 2 ? { region: selects[0].value, province: selects[1].value }
    : level === 3 ? { municipality: selects[2].value } : {};

  const loadLevel = async level => {
    const select = selects[level];
    const controller = new AbortController();
    requests.get(level)?.abort();
    requests.set(level, controller);
    select.disabled = true; select.setAttribute('aria-busy', 'true');
    select.replaceChildren(new Option('Loading…', ''));
    readyText();
    const timeout = window.setTimeout(() => controller.abort(), 45000);
    try {
      const response = await fetch(dataUrl(actions[level], paramsFor(level)), { cache: 'no-store', signal: controller.signal, headers: { Accept: 'application/json' } });
      const json = await response.json();
      if (requests.get(level) !== controller) return false;
      if (!response.ok || !Array.isArray(json.items) || !json.items.length) throw new Error('Location list unavailable.');
      fill(select, json.items, prompts[level]);
      select.disabled = false; select.removeAttribute('aria-invalid');
      errorBox.hidden = true; retry.hidden = true; failedLevel = null;
      meta.textContent = (json.source || 'Philippine geographic reference') + (json.stale ? ' · Using saved data while providers are unavailable.' : json.fromCache ? ' · Cached reference data.' : ' · Location list loaded.')
        + (level === 1 && json.items.length === 1 && json.items[0].code === 'none' ? ' This region has no provinces.' : '');
      return true;
    } catch (error) {
      if (requests.get(level) !== controller) return false;
      select.replaceChildren(new Option('List unavailable', ''));
      select.setAttribute('aria-invalid', 'true');
      errorBox.textContent = 'We could not load ' + actions[level] + '. Check your connection, then retry. Your other answers are kept.';
      errorBox.hidden = false; retry.hidden = false; failedLevel = level;
      meta.textContent = 'Location selection is incomplete. You can still fill in the other sections.';
      return false;
    } finally {
      window.clearTimeout(timeout);
      if (requests.get(level) === controller) { requests.delete(level); select.removeAttribute('aria-busy'); readyText(); }
    }
  };

  const continueCascade = async (level, restoreValues = false, ticket = revision) => {
    for (let index = level; index < selects.length; index++) {
      if (ticket !== revision || !await loadLevel(index) || ticket !== revision) return;
      if (restoreValues) choose(selects[index], savedReport[selects[index].name]);
      // The explicit no-province option is not a fictitious administrative province.
      if (index === 1 && selects[index].options.length === 2 && selects[index].options[1].value === 'none') selects[index].value = 'none';
      syncName(index);
      readyText();
      if (!selects[index].value) return;
    }
  };

  selects.forEach((select, index) => select.addEventListener('change', () => {
    revision++;
    syncName(index);
    resetFrom(index + 1);
    if (select.value && index < selects.length - 1) void continueCascade(index + 1);
    readyText();
  }));
  retry.addEventListener('click', async () => {
    if (failedLevel === null) return;
    const level = failedLevel;
    revision++;
    resetFrom(level);
    retry.disabled = true;
    await continueCascade(level);
    retry.disabled = false;
  });
  form.querySelectorAll('[name="legend"]').forEach(input => input.addEventListener('change', () => setLegend(input.value)));
  specific.addEventListener('change', () => {
    fill(particularType, particulars[specific.value] || [], specific.value ? 'Select particular incident' : 'Select disaster first');
    particularType.disabled = !specific.value;
    updateFlood();
  });
  particularType.addEventListener('change', readyText);
  form.addEventListener('input', readyText);
  form.addEventListener('change', readyText);
  let activeEvidence = null;
  const captureCoordinates = () => {
    latitude.value = '';
    longitude.value = '';
    evidenceCoordinatesOverlay.textContent = '';
    evidenceCoordinatesOverlay.hidden = true;
    coordinateStatus.hidden = false;
    coordinateStatus.textContent = coordinateHelp;
    if (!navigator.geolocation) {
      coordinateStatus.textContent = 'This browser cannot provide device coordinates. The photo can still be submitted.';
      retryCoordinates.hidden = true;
      return;
    }
    coordinateStatus.textContent = 'Requesting your device location…';
    retryCoordinates.hidden = true;
    navigator.geolocation.getCurrentPosition(position => {
      latitude.value = position.coords.latitude.toFixed(7);
      longitude.value = position.coords.longitude.toFixed(7);
      evidenceCoordinatesOverlay.textContent = `Lat ${latitude.value}  |  Lon ${longitude.value}`;
      evidenceCoordinatesOverlay.hidden = false;
      coordinateStatus.textContent = '';
      coordinateStatus.hidden = true;
    }, () => {
      coordinateStatus.textContent = 'Coordinates were not attached. Allow location access, then try again, or submit the photo without coordinates.';
      retryCoordinates.hidden = false;
    }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
  };
  const handleEvidence = source => {
    const file = source.files[0];
    if (evidencePreviewUrl) URL.revokeObjectURL(evidencePreviewUrl);
    evidencePreviewUrl = '';
    evidencePreviewImage.removeAttribute('src');
    evidencePreviewImage.alt = '';
    evidencePreview.hidden = true;
    evidenceCoordinatesOverlay.textContent = '';
    evidenceCoordinatesOverlay.hidden = true;
    coordinateStatus.hidden = false;
    coordinateStatus.textContent = coordinateHelp;
    evidenceDrop.classList.remove('has-file', 'has-error');
    evidence.removeAttribute('aria-invalid');
    evidenceCamera.removeAttribute('aria-invalid');
    if (!file) {
      evidenceStatus.textContent = '';
      return;
    }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size < 1 || file.size > 5 * 1024 * 1024) {
      source.value = '';
      evidenceDrop.classList.add('has-error');
      source.setAttribute('aria-invalid', 'true');
      evidenceStatus.textContent = 'Choose a valid JPG, PNG, or WEBP image no larger than 5 MB.';
      return;
    }
    activeEvidence = source;
    (source === evidence ? evidenceCamera : evidence).value = '';
    evidenceDrop.classList.add('has-file');
    evidenceStatus.textContent = `${source === evidenceCamera ? 'Camera photo' : 'Selected image'}: ${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
    evidencePreviewUrl = URL.createObjectURL(file);
    evidencePreviewImage.src = evidencePreviewUrl;
    evidencePreviewImage.alt = `Preview of selected photo: ${file.name}`;
    evidencePreview.hidden = false;
    captureCoordinates();
  };
  const stopCamera = () => {
    cameraStream?.getTracks().forEach(track => track.stop());
    cameraStream = null;
    cameraVideo.srcObject = null;
    capturePhoto.disabled = true;
    if (cameraDialog.open) cameraDialog.close();
  };
  const openCamera = async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
      evidenceStatus.textContent = 'Live camera access is not available in this browser. Use Choose image instead.';
      return;
    }
    cameraStatus.textContent = 'Requesting camera permission…';
    capturePhoto.disabled = true;
    cameraDialog.showModal();
    try {
      cameraStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
      cameraVideo.srcObject = cameraStream;
      await cameraVideo.play();
      cameraStatus.textContent = 'Camera ready. Hold the device steady, then capture the photo.';
      capturePhoto.disabled = !(cameraVideo.videoWidth && cameraVideo.videoHeight);
      if (capturePhoto.disabled) {
        cameraVideo.addEventListener('loadedmetadata', () => {
          capturePhoto.disabled = false;
        }, { once: true });
      }
    } catch (error) {
      cameraStatus.textContent = error?.name === 'NotAllowedError'
        ? 'Camera permission was blocked. Allow camera access in your browser settings, or use Choose image.'
        : 'The camera could not be started. Check that another app is not using it, or use Choose image.';
      cameraStream?.getTracks().forEach(track => track.stop());
      cameraStream = null;
      cameraVideo.srcObject = null;
    }
  };
  launchCamera.addEventListener('click', () => void openCamera());
  capturePhoto.addEventListener('click', () => {
    const width = cameraVideo.videoWidth;
    const height = cameraVideo.videoHeight;
    if (!width || !height) {
      cameraStatus.textContent = 'The camera is still starting. Wait a moment, then try again.';
      return;
    }
    cameraCanvas.width = width;
    cameraCanvas.height = height;
    cameraCanvas.getContext('2d').drawImage(cameraVideo, 0, 0, width, height);
    cameraStatus.textContent = 'Saving photo…';
    cameraCanvas.toBlob(blob => {
      if (!blob) {
        cameraStatus.textContent = 'The photo could not be captured. Please try again.';
        return;
      }
      const transfer = new DataTransfer();
      transfer.items.add(new File([blob], `camera-${Date.now()}.jpg`, { type: 'image/jpeg', lastModified: Date.now() }));
      evidenceCamera.files = transfer.files;
      handleEvidence(evidenceCamera);
      stopCamera();
      launchCamera.focus();
    }, 'image/jpeg', 0.9);
  });
  closeCamera.addEventListener('click', stopCamera);
  cancelCamera.addEventListener('click', stopCamera);
  cameraDialog.addEventListener('cancel', event => {
    event.preventDefault();
    stopCamera();
  });
  evidence.addEventListener('change', () => handleEvidence(evidence));
  evidenceCamera.addEventListener('change', () => handleEvidence(evidenceCamera));
  retryCoordinates.addEventListener('click', () => {
    if (activeEvidence?.files[0]) captureCoordinates();
  });
  window.addEventListener('pagehide', () => {
    stopCamera();
    if (evidencePreviewUrl) URL.revokeObjectURL(evidencePreviewUrl);
  });
  form.addEventListener('submit', event => {
    if (selects.some(select => select.disabled || !select.value) || requests.size) {
      event.preventDefault();
      errorBox.textContent = 'Complete all four location selections before submitting.';
      errorBox.hidden = false;
      selects.find(select => !select.disabled && !select.value)?.focus();
      return;
    }
    submit.disabled = true;
    submit.textContent = 'Submitting assessment…';
  });
  window.addEventListener('pageshow', readyText);

  ['impactDetail', 'currentSituation', 'reporterDescription'].forEach(id => { byId(id).disabled = true; });
  const legend = savedReport.legend || form.querySelector('[name="legend"]:checked')?.value;
  if (legend) setLegend(legend, true);
  if (savedReport.specific_type) {
    choose(specific, savedReport.specific_type);
    fill(particularType, particulars[specific.value] || [], 'Select particular incident');
    particularType.disabled = !specific.value;
    choose(particularType, savedReport.particular_type);
  }
  updateFlood();
  for (const [key, id] of Object.entries({ water_level: 'waterLevel', water_trend: 'waterTrend', road_passability: 'roadPassability' })) choose(byId(id), savedReport[key]);
  resetFrom(0);
  void continueCascade(0, true);
}
