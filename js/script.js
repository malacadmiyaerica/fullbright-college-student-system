/* ============================================================
   Fullbright College — Student Management System
   Front-end behaviour: enrollment + entrance exam + student dashboard
   ============================================================ */

(function () {
    'use strict';

    /* ---------------- Shared helpers ---------------- */
    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }
    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* ---------------- Modals (exam) ---------------- */
    function bindModals() {
        qsa('[data-close-modal]').forEach(function (el) {
            el.addEventListener('click', function () {
                var modal = el.closest('.modal');
                if (modal) modal.hidden = true;
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            qsa('.modal').forEach(function (m) { m.hidden = true; });
        });
    }

    /* Offline QR code renderer (uses js/qrcode.js). */
    function renderQr(el, text) {
        if (!el || typeof qrcode !== 'function' || !text) return;
        try {
            var qr = qrcode(0, 'M');
            qr.addData(text);
            qr.make();
            el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2 });
        } catch (err) { /* ignore: code access still works */ }
    }

    /* Renders every on-page [data-qr] placeholder (ID cards, receipts). */
    function bindQrCodes() {
        qsa('[data-qr]').forEach(function (el) {
            renderQr(el, el.getAttribute('data-qr'));
        });
    }

    /* Shows/hides the optional profile photo upload field. */
    function syncPhotoChoice() {
        var checked = qs('input[name="profile_photo_opt"]:checked');
        var upload  = document.getElementById('profile-upload-field');
        if (!upload) return;
        upload.classList.toggle('hidden', !(checked && checked.value === 'upload'));
    }
    function bindPhotoChoice() {
        qsa('input[name="profile_photo_opt"]').forEach(function (r) {
            r.addEventListener('change', syncPhotoChoice);
        });
        syncPhotoChoice();
    }

    /* "Same as Permanent Address": mirror + lock the Current Address field.
       Uses `disabled` so a locked field is truly non-editable (the value is
       derived from the Permanent Address on the server). */
    function bindSameAddressBox(box, currInput, permGetter, permInput) {
        if (!box || !currInput) return;

        function sync() {
            var on = box.checked;
            currInput.disabled = on;
            currInput.classList.toggle('is-locked', on);
            if (on) currInput.value = permGetter() || '';
        }

        box.addEventListener('change', sync);
        if (permInput) {
            permInput.addEventListener('input', function () {
                if (box.checked) currInput.value = permGetter() || '';
            });
        }
        sync();
    }

    /* ============================================================
       ENROLLMENT PAGE
       ============================================================ */
    var form = document.getElementById('enrollment-form');

    if (form) {
        bindModals();

        var alertBox = document.getElementById('form-alert');

        /* ----- Email verification (step 2 gate) ----- */
        var verifyStep       = qs('[data-verify-step]');
        var sendVerifyBtn    = document.getElementById('send-verify-btn');
        var verifyEmailInput = document.getElementById('email');
        var verifyStatus     = document.getElementById('verify-status');
        var verifyStatusText = verifyStatus ? qs('.verify-status-text', verifyStatus) : null;
        var verifyDemo       = document.getElementById('verify-demo');
        var verifyDemoLink   = document.getElementById('verify-demo-link');
        var verifiedChip     = qs('[data-verified-email-text]');

        var verifyState = { verified: false, email: null, poll: null };

        function showAlert(kind, text) {
            if (!alertBox) return;
            alertBox.className = 'alert alert-' + kind;
            alertBox.textContent = text;
            alertBox.classList.remove('hidden');
        }

        function setVerifyStatus(kind, text) {
            if (!verifyStatus) return;
            verifyStatus.classList.remove('hidden', 'is-pending', 'is-verified', 'is-error');
            verifyStatus.classList.add('is-' + kind);
            if (verifyStatusText) verifyStatusText.textContent = text;
        }

        function stopVerifyPolling() {
            if (verifyState.poll) { clearInterval(verifyState.poll); verifyState.poll = null; }
        }

        function markVerified(email, silent) {
            verifyState.verified = true;
            verifyState.email = email;
            if (verifyEmailInput) verifyEmailInput.value = email;
            if (verifiedChip) verifiedChip.textContent = email;
            setVerifyStatus('verified', 'Email verified: ' + email);
            if (verifyDemo) verifyDemo.classList.add('hidden');
            stopVerifyPolling();
            if (!silent) showAlert('success', 'Email verified. You may continue.');
        }

        function resetVerification() {
            verifyState.verified = false;
            verifyState.email = null;
            if (verifiedChip) verifiedChip.textContent = '\u00a0';
            stopVerifyPolling();
        }

        function pollVerification() {
            if (verifyState.poll) return;
            verifyState.poll = setInterval(function () {
                fetch('check_verification.php')
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.verified && d.email) markVerified(d.email, false);
                    })
                    .catch(function () { /* keep waiting */ });
            }, 3000);
        }

        function checkVerificationNow() {
            fetch('check_verification.php')
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.verified && d.email) markVerified(d.email, true);
                })
                .catch(function () { /* ignore */ });
        }

        if (sendVerifyBtn) {
            sendVerifyBtn.addEventListener('click', function () {
                var email = (verifyEmailInput.value || '').trim();
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    setVerifyStatus('error', 'Please enter a valid email address.');
                    if (verifyEmailInput) verifyEmailInput.focus();
                    return;
                }

                sendVerifyBtn.disabled = true;
                sendVerifyBtn.textContent = 'Sending...';

                var body = new URLSearchParams();
                body.append('email', email);

                fetch('send_verification.php', { method: 'POST', body: body })
                    .then(function (r) { return r.json().catch(function () { throw new Error('Invalid server response'); }); })
                    .then(function (data) {
                        if (!data.success) {
                            setVerifyStatus('error', data.message || 'Could not send the verification email.');
                            return;
                        }
                        resetVerification();
                        if (verifyEmailInput && data.email) verifyEmailInput.value = data.email;
                        setVerifyStatus('pending', data.message + ' Waiting for verification\u2026');
                        if (data.link) {
                            if (verifyDemo) verifyDemo.classList.remove('hidden');
                            if (verifyDemoLink) verifyDemoLink.setAttribute('href', data.link);
                        }
                        pollVerification();
                    })
                    .catch(function (err) {
                        setVerifyStatus('error', 'Could not send the verification email: ' + err.message);
                    })
                    .finally(function () {
                        sendVerifyBtn.disabled = false;
                        sendVerifyBtn.textContent = 'Send Verification Link';
                    });
            });
        }

        if (verifyEmailInput) {
            verifyEmailInput.addEventListener('input', function () {
                if (verifyState.verified &&
                    verifyEmailInput.value.trim().toLowerCase() !== String(verifyState.email || '').toLowerCase()) {
                    resetVerification();
                    if (verifyDemo) verifyDemo.classList.add('hidden');
                    setVerifyStatus('pending', 'Email changed \u2014 please verify the new address.');
                }
            });
        }

        /* ----- Wizard: show one section at a time ----- */
        var steps = qsa('.wizard-step');
        var currentStep = 0;

        function stepIndex(el) {
            var idx = steps.indexOf(el);
            return idx === -1 ? 0 : idx;
        }

        function showStep(i) {
            currentStep = Math.max(0, Math.min(i, steps.length - 1));
            steps.forEach(function (s, idx) { s.hidden = idx !== currentStep; });
        }

        function stepValid(s) {
            var fields = qsa('input, select, textarea', s);
            for (var i = 0; i < fields.length; i++) {
                if (!fields[i].checkValidity()) {
                    fields[i].reportValidity();
                    return false;
                }
            }
            return true;
        }

        steps.forEach(function (s, idx) {
            qsa('[data-step-next]', s).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!stepValid(s)) return;
                    if (s.hasAttribute('data-verify-step') && !verifyState.verified) {
                        setVerifyStatus('error', 'Please verify your email to proceed.');
                        showAlert('error', 'Please verify your email to proceed.');
                        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                    showStep(idx + 1);
                    qs('.enroll-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
            qsa('[data-step-back]', s).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    showStep(idx - 1);
                    qs('.enroll-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        });

        showStep(0);

        var launchBox     = document.getElementById('exam-launch');
        var summaryBox    = document.getElementById('exam-summary');
        var scoreBox      = document.getElementById('exam-score-box');
        var generateBtn   = document.getElementById('generate-exam-btn');
        var openExamBtn   = document.getElementById('open-exam-btn');
        var examFrame     = document.getElementById('exam-frame');
        var examModal     = document.getElementById('exam-modal');
        var submitBtn     = document.getElementById('submit-application-btn');

        var examState = { code: null, url: null, score: null, total: null };

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            alertBox.className = 'alert hidden';

            if (!verifyState.verified) {
                showAlert('error', 'Please verify your email to proceed.');
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            if (!form.checkValidity()) { form.reportValidity(); return; }

            if (examState.score == null) {
                alertBox.className = 'alert alert-error';
                alertBox.textContent = 'Please generate and take the entrance exam before submitting your application.';
                alertBox.classList.remove('hidden');
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            var body = new FormData(form);
            var original = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            fetch(form.action, { method: 'POST', body: body })
                .then(function (res) { return res.json().catch(function () { throw new Error('Invalid server response'); }); })
                .then(function (data) {
                    alertBox.classList.remove('hidden');
                    if (data.success) {
                        alertBox.className = 'alert alert-success';
                        alertBox.innerHTML = data.message;
                        window.location.href = data.receipt_url || 'receipt.php';
                    } else {
                        alertBox.className = 'alert alert-error';
                        alertBox.textContent = data.message || 'Submission failed.';
                    }
                })
                .catch(function (err) {
                    alertBox.className = 'alert alert-error';
                    alertBox.textContent = 'Something went wrong: ' + err.message;
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = original;
                });
        });

        /* ----- Restore exam state from the server ----- */
        function applyExamState(state) {
            if (state && state.generated) {
                examState.code  = state.code;
                examState.url   = state.exam_url;
                launchBox.classList.add('hidden');
                summaryBox.classList.remove('hidden');
                document.getElementById('exam-code-display').textContent = state.code;
                renderQr(document.getElementById('exam-qr'), state.exam_url);
                document.getElementById('exam-url-link').setAttribute('href', state.exam_url);
                buildScoreSummary();

                if (state.score != null) {
                    finishExam(state.score, state.total || 15, true);
                }
            }
        }

        function buildScoreSummary() {
            summaryBox.classList.remove('hidden');
            document.getElementById('exam-code-display').textContent = examState.code || '';
            renderQr(document.getElementById('exam-qr'), examState.url);
            document.getElementById('exam-url-link').setAttribute('href', examState.url || '#');
        }

        function fetchExamStatus() {
            fetch('get_exam_status.php')
                .then(function (r) { return r.json(); })
                .then(applyExamState)
                .catch(function () { /* keep default state */ });
        }

        /* ----- Generate exam ----- */
        generateBtn.addEventListener('click', function () {
            if (examState.score != null) return;
            generateBtn.disabled = true;
            generateBtn.classList.add('is-loading');
            generateBtn.textContent = 'Generating...';

            fetch('generate_exam.php')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) {
                        window.alert(data.message || 'Could not generate the exam.');
                        return;
                    }
                    examState.code = data.code;
                    examState.url  = data.exam_url;
                    if (data.score != null) {
                        finishExam(data.score, data.total || 15, true);
                        return;
                    }
                    launchBox.classList.add('hidden');
                    buildScoreSummary();
                })
                .catch(function (err) { window.alert('Could not generate the exam: ' + err.message); })
                .finally(function () {
                    generateBtn.disabled = false;
                    generateBtn.classList.remove('is-loading');
                    generateBtn.textContent = 'Generate Exam';
                });
        });

        /* ----- Open exam in modal ----- */
        openExamBtn.addEventListener('click', function () {
            if (!examState.url) return;
            examFrame.src = examState.url;
            examModal.hidden = false;
        });

        /* ----- Exam finished (from iframe) ----- */
        function finishExam(score, total, silent) {
            examState.score = score;
            examState.total = total || 15;

            launchBox.classList.add('hidden');
            summaryBox.classList.add('hidden');
            scoreBox.classList.remove('hidden');
            document.getElementById('exam-score-display').textContent = score + '/' + examState.total;

            submitBtn.disabled = false;
            if (!silent) {
                alertBox.className = 'alert alert-success';
                alertBox.textContent = 'Entrance exam completed with a score of ' + score + '/' + examState.total + '. You may now submit your application.';
                alertBox.classList.remove('hidden');
            }
        }

        window.addEventListener('message', function (e) {
            var d = e.data;
            if (d && d.source === 'fullbright-exam' && d.action === 'done') {
                if (examModal) examModal.hidden = true;
                examFrame.src = 'about:blank';
                if (d.code && d.code === examState.code) {
                    finishExam(d.score, d.total, false);
                } else {
                    examState.code = d.code;
                    finishExam(d.score, d.total, false);
                }
            }
        });

        /* ----- 2x2 photo preview ----- */
        var photoInput = document.getElementById('id_photo');
        var preview = document.getElementById('photo-preview');
        if (photoInput && preview) {
            photoInput.addEventListener('change', function () {
                var f = photoInput.files[0];
                if (!f) return;
                if (!/image\/(jpeg|png)/.test(f.type)) {
                    window.alert('Please choose a JPG or PNG image for your 2x2 photo.');
                    photoInput.value = '';
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (ev) {
                    preview.innerHTML = '<img src="' + ev.target.result + '" alt="2x2 preview">';
                };
                reader.readAsDataURL(f);
            });
        }

        /* ----- Optional profile photo choice ----- */
        bindPhotoChoice();

        /* ----- Same as Permanent Address ----- */
        bindSameAddressBox(
            document.getElementById('same_address'),
            document.getElementById('current_address'),
            function () { return document.getElementById('permanent_address').value; },
            document.getElementById('permanent_address')
        );

        /* ----- Document file inputs show a small tick when filled ----- */
        qsa('.file-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var label = input.closest('.field');
                if (label) label.classList.toggle('has-file', !!input.files.length);
            });
        });

        fetchExamStatus();
        checkVerificationNow();
    }

    /* ============================================================
       STUDENT DASHBOARD
       ============================================================ */
    var profileForm = document.getElementById('profile-form');

    if (profileForm) {
        var profileAlert = document.getElementById('profile-alert');

        bindPhotoChoice();

        /* ----- Same as Permanent Address ----- */
        bindSameAddressBox(
            document.getElementById('p_same_address'),
            document.getElementById('p-current_address'),
            function () { return (window.CURRENT && window.CURRENT.permanentAddress) || ''; }
        );

        profileForm.addEventListener('submit', function (e) {
            e.preventDefault();
            profileAlert.className = 'alert hidden';

            var sameBox = document.getElementById('p_same_address');
            var hasChanges =
                window.CURRENT.email !== document.getElementById('p-email').value ||
                window.CURRENT.currentAddress !== document.getElementById('p-current_address').value ||
                !!window.CURRENT.sameAddress !== !!(sameBox && sameBox.checked) ||
                window.CURRENT.photoChoice !== qs('input[name="profile_photo_opt"]:checked').value ||
                !!qs('#p-photo').files.length;

            if (hasChanges && !window.confirm('Save these profile changes? Your current address and profile photo will be updated.')) {
                return;
            }

            if (!profileForm.checkValidity()) { profileForm.reportValidity(); return; }

            var body = new FormData(profileForm);
            var btn = profileForm.querySelector('button[type="submit"]');
            var original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Saving...';

            fetch('update_profile.php', { method: 'POST', body: body })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    profileAlert.classList.remove('hidden');
                    if (data.success) {
                        profileAlert.className = 'alert alert-success';
                        profileAlert.textContent = data.message;

                        if (data.photo) {
                            var img = document.querySelector('.id-photo img');
                            if (!img) {
                                var holder = document.querySelector('.id-photo');
                                if (holder) {
                                    var ph = holder.querySelector('.photo-placeholder');
                                    if (ph) ph.remove();
                                    img = document.createElement('img');
                                    img.alt = 'Student photo';
                                    holder.insertBefore(img, holder.querySelector('.id-photo-tag'));
                                }
                            }
                            if (img) img.src = data.photo + '?t=' + Date.now();
                        }

                        var tag = document.querySelector('.id-photo-tag');
                        if (tag && data.photo_source) tag.textContent = data.photo_source;

                        var curAddr = document.getElementById('id-current-address');
                        if (curAddr && typeof data.current_address === 'string') {
                            curAddr.textContent = data.current_address !== '' ? data.current_address : '—';
                        }

                        // Single address line when Current = Permanent.
                        var details = document.querySelector('.id-details');
                        if (details) {
                            details.classList.toggle('is-single', !!data.same_address);
                            var permLabel = details.querySelector('.id-detail-perm .id-label');
                            if (permLabel) {
                                permLabel.textContent = data.same_address ? 'Address' : 'Permanent Address';
                            }
                        }

                        if (data.photo_choice) {
                            var choiceRadio = qs('input[name="profile_photo_opt"][value="' + data.photo_choice + '"]');
                            if (choiceRadio) choiceRadio.checked = true;
                            syncPhotoChoice();
                        }

                        var badge = document.getElementById('profile-photo-badge');
                        if (badge && data.photo_choice) {
                            var uploaded = data.photo_choice === 'upload';
                            badge.textContent = uploaded ? 'Uploaded' : 'Skipped — using 2x2 ID photo';
                            badge.className = 'badge ' + (uploaded ? 'badge-approved' : 'badge-type');
                        }
                        var srcEl = document.getElementById('profile-photo-source');
                        if (srcEl && data.photo_source) srcEl.textContent = 'ID picture source: ' + data.photo_source;

                        var current = window.CURRENT || {};
                        current.photo = data.photo;
                        current.photoSource = data.photo_source || current.photoSource;
                        current.photoChoice = data.photo_choice || qs('input[name="profile_photo_opt"]:checked').value;
                        current.currentAddress = data.current_address;
                        current.sameAddress = !!data.same_address;

                        // Refresh the exported ID card (photo/address changed).
                        var idc = window.ID_CARD;
                        if (idc) {
                            if (data.photo) idc.photoUrl = data.photo;
                            if (data.photo_source) idc.photoSource = data.photo_source;
                            if (typeof data.current_address === 'string') {
                                idc.currentAddress = data.current_address !== '' ? data.current_address : '—';
                            }
                            idc.sameAddress = !!data.same_address;
                        }
                        runIdCardExport();
                    } else {
                        profileAlert.className = 'alert alert-error';
                        profileAlert.textContent = data.message;
                    }
                })
                .catch(function (err) {
                    profileAlert.className = 'alert alert-error';
                    profileAlert.textContent = 'Request failed: ' + err.message;
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = original;
                });
        });
    }

    var passwordForm = document.getElementById('password-form');

    if (passwordForm) {
        var pwdAlert = document.getElementById('pwd-alert');

        passwordForm.addEventListener('submit', function (e) {
            e.preventDefault();
            pwdAlert.className = 'alert hidden';

            var cur = document.getElementById('pw-current').value;
            var pw  = document.getElementById('pw-new').value;
            var cf  = document.getElementById('pw-confirm').value;

            if (pw !== cf) {
                pwdAlert.className = 'alert alert-error';
                pwdAlert.textContent = 'New password and confirmation do not match.';
                pwdAlert.classList.remove('hidden');
                return;
            }

            var body = new URLSearchParams();
            body.append('current', cur);
            body.append('new', pw);
            body.append('confirm', cf);

            var btn = passwordForm.querySelector('button[type="submit"]');
            var original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Saving...';

            fetch('change_password.php', { method: 'POST', body: body })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    pwdAlert.classList.remove('hidden');
                    if (data.success) {
                        pwdAlert.className = 'alert alert-success';
                        pwdAlert.textContent = data.message;
                        passwordForm.reset();
                    } else {
                        pwdAlert.className = 'alert alert-error';
                        pwdAlert.textContent = data.message;
                    }
                })
                .catch(function (err) {
                    pwdAlert.className = 'alert alert-error';
                    pwdAlert.textContent = 'Request failed: ' + err.message;
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = original;
                });
        });
    }

    /* ============================================================
       DIGITAL ID — generate a downloadable PNG from the card data
       ============================================================ */
    function loadImage(src) {
        return new Promise(function (resolve) {
            if (!src) { resolve(null); return; }
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () { resolve(img); };
            img.onerror = function () { resolve(null); };
            img.src = src;
        });
    }

    function roundRectPath(ctx, x, y, w, h, r) {
        r = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function drawCoverImage(ctx, img, x, y, w, h) {
        var ir = img.width / img.height;
        var br = w / h;
        var dw, dh, dx, dy;
        if (ir > br) { dh = h; dw = h * ir; dx = x - (dw - w) / 2; dy = y; }
        else { dw = w; dh = w / ir; dx = x; dy = y - (dh - h) / 2; }
        ctx.save();
        roundRectPath(ctx, x, y, w, h, 10);
        ctx.clip();
        ctx.drawImage(img, dx, dy, dw, dh);
        ctx.restore();
        roundRectPath(ctx, x, y, w, h, 10);
        ctx.lineWidth = 5;
        ctx.strokeStyle = '#e4b23f';
        ctx.stroke();
    }

    function wrapAll(ctx, text, maxWidth) {
        var words = String(text == null ? '' : text).replace(/\s+/g, ' ').trim().split(' ').filter(Boolean);
        var lines = [], line = '';
        for (var i = 0; i < words.length; i++) {
            var test = line ? line + ' ' + words[i] : words[i];
            if (ctx.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = words[i];
            } else {
                line = test;
            }
        }
        if (line) lines.push(line);
        return lines;
    }

    function ellipsize(ctx, text, maxWidth) {
        var t = String(text);
        if (ctx.measureText(t).width <= maxWidth) return t;
        while (t.length > 1 && ctx.measureText(t + '…').width > maxWidth) t = t.slice(0, -1);
        return t + '…';
    }

    function capLines(ctx, lines, maxWidth, maxLines) {
        if (lines.length <= maxLines) return lines;
        var capped = lines.slice(0, maxLines);
        capped[maxLines - 1] = ellipsize(ctx, capped[maxLines - 1] + ' ' + lines.slice(maxLines).join(' '), maxWidth);
        return capped;
    }

    // Shrinks a font size until the wrapped text fits the given width, line
    // count and (optional) height, so long names/addresses never get cut off.
    function fitBlock(ctx, text, opts) {
        var ratio = opts.lineRatio || 1.25;
        var size, lines, widest, totalH, i;
        for (size = opts.maxPx; size >= opts.minPx; size--) {
            ctx.font = opts.fontFn(size);
            lines = wrapAll(ctx, text, opts.maxWidth);
            if (!lines.length) lines = [''];
            widest = 0;
            for (i = 0; i < lines.length; i++) widest = Math.max(widest, ctx.measureText(lines[i]).width);
            totalH = lines.length * size * ratio;
            if (lines.length <= opts.maxLines && widest <= opts.maxWidth + 0.5 &&
                (opts.maxHeight == null || totalH <= opts.maxHeight)) {
                return { size: size, lines: lines, height: totalH, fontFn: opts.fontFn };
            }
        }
        ctx.font = opts.fontFn(opts.minPx);
        lines = capLines(ctx, wrapAll(ctx, text, opts.maxWidth), opts.maxWidth, opts.maxLines);
        if (!lines.length) lines = [''];
        return { size: opts.minPx, lines: lines, height: lines.length * opts.minPx * ratio, fontFn: opts.fontFn };
    }

    function drawFieldLabel(ctx, label, x, y) {
        ctx.fillStyle = '#9fb0d8';
        ctx.font = 'bold 12px "Segoe UI", Arial, sans-serif';
        ctx.textBaseline = 'alphabetic';
        ctx.fillText(String(label).toUpperCase(), x, y);
    }

    function drawFitLines(ctx, fit, x, top, color) {
        ctx.fillStyle = color;
        ctx.font = fit.fontFn(fit.size);
        var lh = fit.size * 1.25;
        for (var i = 0; i < fit.lines.length; i++) {
            ctx.fillText(fit.lines[i], x, top + fit.size + i * lh);
        }
    }

    // Draws a label above an auto-fitted value and returns the next free Y,
    // so stacked fields can never overlap regardless of text length.
    function drawFitField(ctx, x, y, w, label, text, opts, color) {
        drawFieldLabel(ctx, label, x, y + 13);
        opts.maxWidth = w;
        var fit = fitBlock(ctx, text, opts);
        drawFitLines(ctx, fit, x, y + 20, color);
        return y + 20 + fit.height;
    }

    function buildIdCard(data) {
        var W = 1012, H = 638;
        var canvas = document.createElement('canvas');
        canvas.width = W; canvas.height = H;
        var ctx = canvas.getContext('2d');

        var NAVY = '#0f2a5c', GOLD = '#e4b23f', WHITE = '#ffffff', MUTED = '#c8d3ec';
        var SANS = '"Segoe UI", Arial, sans-serif';
        function sans(weight, size) { return weight + ' ' + size + 'px ' + SANS; }

        ctx.textAlign = 'left';
        ctx.textBaseline = 'alphabetic';

        // Card background (deep blue gradient).
        var g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, '#0f2a5c');
        g.addColorStop(0.55, '#0c2150');
        g.addColorStop(1, '#081b3e');
        roundRectPath(ctx, 0, 0, W, H, 24);
        ctx.fillStyle = g;
        ctx.fill();

        // Header band + gold rule + soft gold glow behind the QR corner.
        ctx.save();
        roundRectPath(ctx, 0, 0, W, H, 24);
        ctx.clip();
        ctx.fillStyle = NAVY;
        ctx.fillRect(0, 0, W, 96);
        ctx.fillStyle = GOLD;
        ctx.fillRect(0, 96, W, 4);
        var glow = ctx.createRadialGradient(W, 0, 0, W, 0, 380);
        glow.addColorStop(0, 'rgba(228, 178, 63, 0.20)');
        glow.addColorStop(1, 'rgba(228, 178, 63, 0)');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, W, 380);
        ctx.restore();

        // Emblem on a white circle with a gold ring.
        if (data.logo) {
            var lx = 30, ly = 18, ls = 60;
            ctx.save();
            ctx.beginPath();
            ctx.arc(lx + ls / 2, ly + ls / 2, ls / 2, 0, Math.PI * 2);
            ctx.closePath();
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.clip();
            ctx.drawImage(data.logo, lx + 3, ly + 3, ls - 6, ls - 6);
            ctx.restore();
            ctx.beginPath();
            ctx.arc(lx + ls / 2, ly + ls / 2, ls / 2, 0, Math.PI * 2);
            ctx.lineWidth = 3;
            ctx.strokeStyle = GOLD;
            ctx.stroke();
        }

        // Header text.
        ctx.fillStyle = WHITE;
        ctx.font = sans('bold', 26);
        ctx.fillText('FULLBRIGHT COLLEGE', 108, 46);
        ctx.fillStyle = GOLD;
        ctx.font = sans('bold', 13);
        ctx.fillText('OFFICIAL STUDENT ID', 109, 73);

        // Photo (2x2) top-left of the body.
        var px = 40, py = 120, pw = 200, ph = 200;
        if (data.photo) {
            drawCoverImage(ctx, data.photo, px, py, pw, ph);
        } else {
            ctx.fillStyle = WHITE;
            roundRectPath(ctx, px, py, pw, ph, 10);
            ctx.fill();
            ctx.fillStyle = '#b8c1d6';
            ctx.font = 'bold 34px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('2x2', px + pw / 2, py + ph / 2);
            ctx.textAlign = 'left';
            ctx.textBaseline = 'alphabetic';
            roundRectPath(ctx, px, py, pw, ph, 10);
            ctx.lineWidth = 5;
            ctx.strokeStyle = GOLD;
            ctx.stroke();
        }
        ctx.fillStyle = 'rgba(8, 27, 62, 0.85)';
        ctx.fillRect(px, py + ph - 26, pw, 26);
        ctx.fillStyle = GOLD;
        ctx.font = sans('bold', 12);
        ctx.textAlign = 'center';
        ctx.fillText(String(data.photoSource || '2x2 ID Photo').toUpperCase(), px + pw / 2, py + ph - 9);
        ctx.textAlign = 'left';

        // QR (full-ID download) at the top-right, on the same band as the photo.
        var qx = 832, qy = 120, qs = 144;
        ctx.fillStyle = WHITE;
        roundRectPath(ctx, qx, qy, qs, qs, 10);
        ctx.fill();
        if (data.qr) {
            ctx.save();
            roundRectPath(ctx, qx, qy, qs, qs, 10);
            ctx.clip();
            ctx.drawImage(data.qr, qx + 8, qy + 8, qs - 16, qs - 16);
            ctx.restore();
        }
        roundRectPath(ctx, qx, qy, qs, qs, 10);
        ctx.lineWidth = 3;
        ctx.strokeStyle = GOLD;
        ctx.stroke();
        ctx.fillStyle = GOLD;
        ctx.font = sans('bold', 12);
        ctx.textAlign = 'center';
        ctx.fillText('SCAN TO DOWNLOAD.', qx + qs / 2, qy + qs + 20);
        ctx.textAlign = 'left';

        // Core info between the photo and the QR — auto-fitted so text never overlaps.
        var ix = 276, iw = 540, iy = 116;
        iy = drawFitField(ctx, ix, iy, iw, 'Name', data.name,
            { maxLines: 2, maxPx: 30, minPx: 18, fontFn: function (s) { return sans('bold', s); } }, WHITE) + 12;
        iy = drawFitField(ctx, ix, iy, iw, 'Student No.', data.studentId,
            { maxLines: 1, maxPx: 30, minPx: 18, fontFn: function (s) { return 'bold ' + s + 'px "Courier New", monospace'; } }, GOLD) + 14;

        var half = (iw - 20) / 2, col2 = ix + half + 20;
        drawFieldLabel(ctx, 'Course / Program', ix, iy + 13);
        drawFieldLabel(ctx, 'Department', col2, iy + 13);
        var progFit = fitBlock(ctx, data.program,
            { maxWidth: half, maxLines: 2, maxPx: 18, minPx: 12, fontFn: function (s) { return sans('600', s); } });
        var deptFit = fitBlock(ctx, data.department,
            { maxWidth: half, maxLines: 2, maxPx: 18, minPx: 12, fontFn: function (s) { return sans('600', s); } });
        drawFitLines(ctx, progFit, ix, iy + 20, WHITE);
        drawFitLines(ctx, deptFit, col2, iy + 20, WHITE);

        // Divider above the details grid.
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.18)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(40, 372);
        ctx.lineTo(W - 40, 372);
        ctx.stroke();

        // Details grid: addresses + emergency contact (auto-fitted).
        function detailCell(label, value, x, y, maxWidth) {
            drawFieldLabel(ctx, label, x, y + 14);
            var fit = fitBlock(ctx, value,
                { maxWidth: maxWidth || 452, maxLines: 3, maxPx: 17, minPx: 12, fontFn: function (s) { return sans('600', s); } });
            drawFitLines(ctx, fit, x, y + 22, WHITE);
        }
        if (data.sameAddress) {
            // Same as Permanent Address — show a single address line.
            detailCell('Address', data.permanentAddress, 40, 388, 932);
            detailCell('Emergency Contact Name', data.emergencyName, 40, 476);
            detailCell('Emergency Contact Number', data.emergencyNo, 520, 476);
        } else {
            detailCell('Permanent Address', data.permanentAddress, 40, 388);
            detailCell('Current Address', data.currentAddress, 520, 388);
            detailCell('Emergency Contact Name', data.emergencyName, 40, 476);
            detailCell('Emergency Contact Number', data.emergencyNo, 520, 476);
        }

        // Footer band.
        ctx.save();
        roundRectPath(ctx, 0, 0, W, H, 24);
        ctx.clip();
        ctx.fillStyle = GOLD;
        ctx.fillRect(0, 584, W, 3);
        ctx.fillStyle = 'rgba(0, 0, 0, 0.28)';
        ctx.fillRect(0, 587, W, H - 587);
        ctx.restore();
        ctx.fillStyle = MUTED;
        ctx.font = sans('400', 15);
        ctx.fillText('Valid ID — ' + String(data.valid || ''), 40, 617);
        ctx.textAlign = 'right';
        ctx.fillText(String(data.status || ''), W - 40, 617);
        ctx.textAlign = 'left';

        return canvas;
    }

    function runIdCardExport() {
        var data = window.ID_CARD;
        if (!data || !data.studentId) return;

        var qrDataUrl = null;
        if (typeof qrcode === 'function' && data.qrUrl) {
            try {
                var qr = qrcode(0, 'M');
                qr.addData(data.qrUrl);
                qr.make();
                qrDataUrl = qr.createDataURL(8, 2);
            } catch (err) { /* QR image is optional for the download */ }
        }

        Promise.all([
            loadImage(data.logoSrc || 'assets/logo.png'),
            loadImage(data.photoUrl || ''),
            loadImage(qrDataUrl || '')
        ]).then(function (imgs) {
            data.logo = imgs[0];
            data.photo = imgs[1];
            data.qr = imgs[2];

            var status = document.getElementById('digital-id-status');
            var dl = document.getElementById('download-id-btn');

            try {
                var url = buildIdCard(data).toDataURL('image/png');

                // Keep the Download button pointing at the freshest image.
                if (dl) {
                    dl._url = url;
                    if (!dl._bound) {
                        dl.addEventListener('click', function () {
                            if (!dl._url) return;
                            var a = document.createElement('a');
                            a.href = dl._url;
                            a.download = 'fullbright-id-' + (String(data.studentId || 'student').replace(/[^A-Za-z0-9]/g, '') || 'student') + '.png';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        });
                        dl._bound = true;
                    }
                }

                // Persist the card to the server so the QR on it downloads
                // exactly this image when scanned from a phone.
                if (typeof fetch === 'function') {
                    fetch('save_id_card.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ sid: data.studentId, image: url })
                    }).catch(function () { /* non-fatal: button still works */ });
                }

                if (status) status.textContent = 'Your ID is ready to download or print.';
            } catch (err) {
                if (dl) dl.disabled = true;
                if (status) status.textContent = 'Image export is unavailable in this browser — use the Print button to save your ID.';
            }
        });
    }

    function initDigitalId() {
        if (window.ID_CARD) runIdCardExport();
    }

    bindQrCodes();
    initDigitalId();
})();