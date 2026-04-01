(function () {
    function initBookingComponent(rootNode) {
        var stateNode = rootNode.querySelector('.js-skds-booking-state');
        var messageNode = rootNode.querySelector('.js-skds-booking-messages');

        if (!stateNode) {
            return;
        }

        var payload;
        var messages = {};

        try {
            payload = JSON.parse(stateNode.textContent);
        } catch (error) {
            return;
        }

        if (messageNode) {
            try {
                messages = JSON.parse(messageNode.textContent);
            } catch (error) {
                messages = {};
            }
        }

        var refs = {
            specializationContainer: rootNode.querySelector('[data-role="specializations"]'),
            doctorContainer: rootNode.querySelector('[data-role="doctors"]'),
            appointmentTypeContainer: rootNode.querySelector('[data-role="appointment-types"]'),
            serviceContainer: rootNode.querySelector('[data-role="services"]'),
            summaryContainer: rootNode.querySelector('[data-role="summary"]'),
            dateContainer: rootNode.querySelector('[data-role="dates"]'),
            locationContainer: rootNode.querySelector('[data-role="location"]'),
            locationNoteContainer: rootNode.querySelector('[data-role="location-note"]'),
            slotContainer: rootNode.querySelector('[data-role="slots"]'),
            selectionContainer: rootNode.querySelector('[data-role="selection"]'),
            modalNode: rootNode.querySelector('[data-role="booking-modal"]'),
            successModalNode: rootNode.querySelector('[data-role="booking-success-modal"]'),
            consentModalNode: rootNode.querySelector('[data-role="consent-modal"]'),
            modalSummaryNode: rootNode.querySelector('[data-role="modal-summary"]'),
            modalFeedbackNode: rootNode.querySelector('[data-role="modal-feedback"]'),
            successModalSummaryNode: rootNode.querySelector('[data-role="success-modal-summary"]'),
            successModalMessageNode: rootNode.querySelector('[data-role="success-modal-message"]'),
            bookingFormNode: rootNode.querySelector('[data-role="booking-form"]'),
            modalSubmitNode: rootNode.querySelector('[data-role="modal-submit"]'),
            patientNameNode: rootNode.querySelector('[data-role="field-patient-name"]'),
            patientPhoneNode: rootNode.querySelector('[data-role="field-patient-phone"]'),
            patientEmailNode: rootNode.querySelector('[data-role="field-patient-email"]'),
            patientCommentNode: rootNode.querySelector('[data-role="field-patient-comment"]'),
            patientConsentNode: rootNode.querySelector('[data-role="field-patient-consent"]'),
            patientNameErrorNode: rootNode.querySelector('[data-role="error-patient-name"]'),
            patientPhoneErrorNode: rootNode.querySelector('[data-role="error-patient-phone"]'),
            patientEmailErrorNode: rootNode.querySelector('[data-role="error-patient-email"]'),
            patientConsentErrorNode: rootNode.querySelector('[data-role="error-patient-consent"]'),
            slotErrorNode: rootNode.querySelector('[data-role="error-slot"]')
        };

        var state = {
            selectedSpecializationId: null,
            selectedDoctorId: payload.initialDoctorId || null,
            selectedAppointmentTypeId: null,
            selectedServiceId: null,
            selectedLocationKey: null,
            selectedDate: null,
            selectedSlotKey: null
        };

        var runtime = {
            isModalOpen: false,
            isSuccessModalOpen: false,
            isConsentModalOpen: false,
            isSubmitting: false,
            modalMessage: '',
            modalMessageType: 'error',
            flashMessage: '',
            flashType: 'success',
            successBookingIntent: null,
            successMessage: '',
            bookingForm: {
                patientName: '',
                patientPhone: '',
                patientEmail: '',
                patientComment: '',
                patientConsent: false
            },
            bookingErrors: {}
        };

        var weekdayLabels = Array.isArray(messages.weekdayLabels) ? messages.weekdayLabels : [];
        var monthLabels = Array.isArray(messages.monthLabels) ? messages.monthLabels : [];

        function toIdMap(items) {
            return items.reduce(function (accumulator, item) {
                accumulator[item.id] = item;

                return accumulator;
            }, {});
        }

        var specializationMap = toIdMap(payload.specializations);
        var doctorMap = toIdMap(payload.doctors);
        var appointmentTypeMap = toIdMap(payload.appointmentTypes);
        var serviceMap = toIdMap(payload.services);
        var locationMap = toIdMap(payload.locations);

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatPrice(price, currency) {
            var amount = Number(price);

            if (Number.isNaN(amount)) {
                return price + ' ' + currency;
            }

            var formatted = amount.toLocaleString('ru-RU', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });

            if (currency === 'RUB') {
                return formatted + ' \u20BD';
            }

            return formatted + ' ' + currency;
        }

        function formatMinutes(totalMinutes) {
            var hours = Math.floor(totalMinutes / 60);
            var minutes = totalMinutes % 60;

            return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
        }

        function toIsoDate(dateObject) {
            var year = dateObject.getFullYear();
            var month = String(dateObject.getMonth() + 1).padStart(2, '0');
            var day = String(dateObject.getDate()).padStart(2, '0');

            return year + '-' + month + '-' + day;
        }

        function parseIsoDate(dateValue) {
            var parts = dateValue.split('-');

            return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        }

        function getWeekdayNumber(dateObject) {
            var weekday = dateObject.getDay();

            return weekday === 0 ? 7 : weekday;
        }

        function getLocationKey(locationId) {
            return locationId === null ? 'none' : String(locationId);
        }

        function getMessage(key, fallback) {
            if (Object.prototype.hasOwnProperty.call(messages, key) && messages[key] !== '') {
                return messages[key];
            }

            return fallback;
        }

        function getPhoneDigits(value) {
            return String(value).replace(/\D+/g, '');
        }

        function formatPhoneValue(value) {
            var digits = getPhoneDigits(value);

            if (digits === '') {
                return '';
            }

            if (digits.charAt(0) === '8') {
                digits = '7' + digits.slice(1);
            } else if (digits.charAt(0) !== '7') {
                digits = '7' + digits;
            }

            digits = digits.slice(0, 11);

            var localDigits = digits.slice(1);
            var formatted = '+7';

            if (localDigits.length > 0) {
                formatted += ' (' + localDigits.slice(0, 3);
            }

            if (localDigits.length >= 3) {
                formatted += ')';
            }

            if (localDigits.length > 3) {
                formatted += ' ' + localDigits.slice(3, 6);
            }

            if (localDigits.length > 6) {
                formatted += '-' + localDigits.slice(6, 8);
            }

            if (localDigits.length > 8) {
                formatted += '-' + localDigits.slice(8, 10);
            }

            return formatted;
        }

        function resetBookingForm() {
            runtime.bookingForm = {
                patientName: '',
                patientPhone: '',
                patientEmail: '',
                patientComment: '',
                patientConsent: false
            };
        }

        function clearFlashMessage() {
            runtime.flashMessage = '';
            runtime.flashType = 'success';
        }

        function clearModalErrors() {
            runtime.modalMessage = '';
            runtime.modalMessageType = 'error';
            runtime.bookingErrors = {};
        }

        function getSpecializationNameList(doctor) {
            return doctor.specializationIds
                .map(function (specializationId) {
                    return specializationMap[specializationId] ? specializationMap[specializationId].name : '';
                })
                .filter(Boolean);
        }

        function getUsedSpecializations() {
            return payload.specializations.filter(function (specialization) {
                return payload.doctors.some(function (doctor) {
                    return doctor.specializationIds.indexOf(specialization.id) !== -1;
                });
            });
        }

        function getAvailableDoctors() {
            return payload.doctors.filter(function (doctor) {
                if (state.selectedSpecializationId === null) {
                    return true;
                }

                return doctor.specializationIds.indexOf(state.selectedSpecializationId) !== -1;
            });
        }

        function getDoctorServicePrices() {
            return payload.servicePrices.filter(function (servicePrice) {
                return servicePrice.doctorId === state.selectedDoctorId;
            });
        }

        function getAvailableAppointmentTypes() {
            var seenMap = {};

            return getDoctorServicePrices().filter(function (servicePrice) {
                if (seenMap[servicePrice.appointmentTypeId]) {
                    return false;
                }

                seenMap[servicePrice.appointmentTypeId] = true;

                return !!appointmentTypeMap[servicePrice.appointmentTypeId];
            }).map(function (servicePrice) {
                return appointmentTypeMap[servicePrice.appointmentTypeId];
            });
        }

        function getAppointmentTypeServicePrices() {
            return getDoctorServicePrices().filter(function (servicePrice) {
                return servicePrice.appointmentTypeId === state.selectedAppointmentTypeId;
            });
        }

        function getAvailableServices() {
            var seenMap = {};

            return getAppointmentTypeServicePrices().filter(function (servicePrice) {
                if (seenMap[servicePrice.serviceId]) {
                    return false;
                }

                seenMap[servicePrice.serviceId] = true;

                return !!serviceMap[servicePrice.serviceId];
            }).map(function (servicePrice) {
                return serviceMap[servicePrice.serviceId];
            });
        }

        function getSelectedServicePrices() {
            return getAppointmentTypeServicePrices().filter(function (servicePrice) {
                return servicePrice.serviceId === state.selectedServiceId;
            });
        }

        function getAvailableLocations(selectedServicePrices) {
            var seenMap = {};

            return selectedServicePrices.filter(function (servicePrice) {
                var locationKey = getLocationKey(servicePrice.locationId);

                if (seenMap[locationKey]) {
                    return false;
                }

                seenMap[locationKey] = true;

                return true;
            }).map(function (servicePrice) {
                var appointmentType = appointmentTypeMap[servicePrice.appointmentTypeId];
                var location = servicePrice.locationId !== null ? locationMap[servicePrice.locationId] : null;

                return {
                    key: getLocationKey(servicePrice.locationId),
                    locationId: servicePrice.locationId,
                    name: location ? location.name : appointmentType.name,
                    address: location ? location.address : ''
                };
            });
        }

        function getSelectedServicePrice() {
            var selectedServicePrices = getSelectedServicePrices();

            for (var index = 0; index < selectedServicePrices.length; index += 1) {
                if (getLocationKey(selectedServicePrices[index].locationId) === state.selectedLocationKey) {
                    return selectedServicePrices[index];
                }
            }

            return selectedServicePrices.length > 0 ? selectedServicePrices[0] : null;
        }

        function hasBookingConflict(dateValue, doctorId, slotFrom, slotTo) {
            return payload.bookings.some(function (booking) {
                if (booking.doctorId !== doctorId || booking.bookingDate !== dateValue) {
                    return false;
                }

                return slotFrom < booking.timeToMinutes && slotTo > booking.timeFromMinutes;
            });
        }

        function buildAvailability(servicePrice) {
            if (!servicePrice) {
                return [];
            }

            var today = parseIsoDate(payload.today);
            var now = new Date();
            var currentTimeMinutes = now.getHours() * 60 + now.getMinutes();
            var duration = servicePrice.durationMinutes;
            var rules = payload.scheduleRules.filter(function (scheduleRule) {
                return scheduleRule.doctorId === state.selectedDoctorId;
            });
            var availability = [];

            for (var dayOffset = 0; dayOffset < 28; dayOffset += 1) {
                var currentDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + dayOffset);
                var currentWeekday = getWeekdayNumber(currentDate);
                var dateRules = rules.filter(function (scheduleRule) {
                    return scheduleRule.weekday === currentWeekday;
                });

                if (dateRules.length === 0) {
                    continue;
                }

                var slotMap = {};

                dateRules.forEach(function (scheduleRule) {
                    for (
                        var slotStart = scheduleRule.timeFromMinutes;
                        slotStart + duration <= scheduleRule.timeToMinutes;
                        slotStart += duration
                    ) {
                        var slotEnd = slotStart + duration;
                        var dateValue = toIsoDate(currentDate);
                        var slotKey = dateValue + '-' + slotStart + '-' + slotEnd;

                        if (dateValue === payload.today && slotStart <= currentTimeMinutes) {
                            continue;
                        }

                        if (hasBookingConflict(dateValue, state.selectedDoctorId, slotStart, slotEnd)) {
                            continue;
                        }

                        slotMap[slotKey] = {
                            key: slotKey,
                            date: dateValue,
                            timeFromMinutes: slotStart,
                            timeToMinutes: slotEnd
                        };
                    }
                });

                var slots = Object.keys(slotMap).map(function (slotKey) {
                    return slotMap[slotKey];
                }).sort(function (leftSlot, rightSlot) {
                    return leftSlot.timeFromMinutes - rightSlot.timeFromMinutes;
                });

                if (slots.length === 0) {
                    continue;
                }

                availability.push({
                    date: toIsoDate(currentDate),
                    dayLabel: weekdayLabels[currentDate.getDay()],
                    fullLabel: currentDate.getDate() + ' ' + monthLabels[currentDate.getMonth()],
                    slots: slots
                });

                if (availability.length >= 8) {
                    break;
                }
            }

            return availability;
        }

        function syncState() {
            var usedSpecializations = getUsedSpecializations();

            if (
                state.selectedSpecializationId === null
                || !usedSpecializations.some(function (specialization) {
                    return specialization.id === state.selectedSpecializationId;
                })
            ) {
                state.selectedSpecializationId = usedSpecializations.length > 0 ? usedSpecializations[0].id : null;
            }

            var availableDoctors = getAvailableDoctors();

            if (
                state.selectedDoctorId === null
                || !availableDoctors.some(function (doctor) {
                    return doctor.id === state.selectedDoctorId;
                })
            ) {
                state.selectedDoctorId = availableDoctors.length > 0 ? availableDoctors[0].id : null;
            }

            var availableAppointmentTypes = getAvailableAppointmentTypes();

            if (
                state.selectedAppointmentTypeId === null
                || !availableAppointmentTypes.some(function (appointmentType) {
                    return appointmentType.id === state.selectedAppointmentTypeId;
                })
            ) {
                state.selectedAppointmentTypeId = availableAppointmentTypes.length > 0 ? availableAppointmentTypes[0].id : null;
            }

            var availableServices = getAvailableServices();

            if (
                state.selectedServiceId === null
                || !availableServices.some(function (service) {
                    return service.id === state.selectedServiceId;
                })
            ) {
                state.selectedServiceId = availableServices.length > 0 ? availableServices[0].id : null;
            }

            var availableLocations = getAvailableLocations(getSelectedServicePrices());

            if (
                state.selectedLocationKey === null
                || !availableLocations.some(function (location) {
                    return location.key === state.selectedLocationKey;
                })
            ) {
                state.selectedLocationKey = availableLocations.length > 0 ? availableLocations[0].key : null;
            }

            var selectedServicePrice = getSelectedServicePrice();
            var availability = buildAvailability(selectedServicePrice);

            if (
                state.selectedDate === null
                || !availability.some(function (dateItem) {
                    return dateItem.date === state.selectedDate;
                })
            ) {
                state.selectedDate = availability.length > 0 ? availability[0].date : null;
            }

            var selectedDateItem = availability.find(function (dateItem) {
                return dateItem.date === state.selectedDate;
            }) || null;

            if (
                state.selectedSlotKey !== null
                && (
                    selectedDateItem === null
                    || !selectedDateItem.slots.some(function (slot) {
                        return slot.key === state.selectedSlotKey;
                    })
                )
            ) {
                state.selectedSlotKey = null;
            }

            return {
                usedSpecializations: usedSpecializations,
                availableDoctors: availableDoctors,
                availableAppointmentTypes: availableAppointmentTypes,
                availableServices: availableServices,
                availableLocations: availableLocations,
                selectedServicePrice: selectedServicePrice,
                availability: availability
            };
        }

        function buildBookingIntentDetail(prepared) {
            var selectedDoctor = doctorMap[state.selectedDoctorId] || null;
            var selectedService = serviceMap[state.selectedServiceId] || null;
            var selectedAppointmentType = appointmentTypeMap[state.selectedAppointmentTypeId] || null;
            var selectedDateItem = prepared.availability.find(function (dateItem) {
                return dateItem.date === state.selectedDate;
            }) || null;
            var selectedSlot = selectedDateItem ? selectedDateItem.slots.find(function (slot) {
                return slot.key === state.selectedSlotKey;
            }) : null;
            var selectedLocation = prepared.selectedServicePrice && prepared.selectedServicePrice.locationId !== null
                ? (locationMap[prepared.selectedServicePrice.locationId] || null)
                : null;

            if (
                !selectedDoctor
                || !selectedService
                || !selectedAppointmentType
                || !prepared.selectedServicePrice
                || !selectedDateItem
                || !selectedSlot
            ) {
                return null;
            }

            return {
                doctor: {
                    id: selectedDoctor.id,
                    name: selectedDoctor.name
                },
                service: {
                    id: selectedService.id,
                    name: selectedService.name
                },
                appointmentType: {
                    id: selectedAppointmentType.id,
                    name: selectedAppointmentType.name
                },
                location: {
                    key: state.selectedLocationKey,
                    id: prepared.selectedServicePrice.locationId,
                    name: selectedLocation ? selectedLocation.name : selectedAppointmentType.name,
                    address: selectedLocation ? selectedLocation.address : ''
                },
                servicePrice: {
                    id: prepared.selectedServicePrice.id,
                    price: prepared.selectedServicePrice.price,
                    currency: prepared.selectedServicePrice.currency,
                    durationMinutes: prepared.selectedServicePrice.durationMinutes
                },
                bookingDate: selectedDateItem.date,
                bookingDateLabel: selectedDateItem.fullLabel,
                slot: {
                    key: selectedSlot.key,
                    label: formatMinutes(selectedSlot.timeFromMinutes),
                    timeFromMinutes: selectedSlot.timeFromMinutes,
                    timeToMinutes: selectedSlot.timeToMinutes
                }
            };
        }

        function renderSpecializations(usedSpecializations) {
            refs.specializationContainer.innerHTML = usedSpecializations.map(function (specialization) {
                return '' +
                    '<button class="skds-booking__chip' + (specialization.id === state.selectedSpecializationId ? ' is-active' : '') + '" ' +
                    'type="button" data-specialization-id="' + specialization.id + '">' +
                    escapeHtml(specialization.name) +
                    '</button>';
            }).join('');
        }

        function renderDoctors(availableDoctors) {
            if (availableDoctors.length === 0) {
                refs.doctorContainer.innerHTML =
                    '<div class="skds-booking__placeholder">' + escapeHtml(getMessage('noDoctors', '')) + '</div>';

                return;
            }

            refs.doctorContainer.innerHTML = availableDoctors.map(function (doctor) {
                var specializationNames = getSpecializationNameList(doctor);

                return '' +
                    '<button class="skds-booking__doctor' + (doctor.id === state.selectedDoctorId ? ' is-active' : '') + '" ' +
                    'type="button" data-doctor-id="' + doctor.id + '">' +
                    '<div class="skds-booking__doctor-name">' + escapeHtml(doctor.name) + '</div>' +
                    '<div class="skds-booking__doctor-meta">' + escapeHtml(specializationNames.join(', ')) + '</div>' +
                    '</button>';
            }).join('');
        }

        function renderAppointmentTypes(availableAppointmentTypes) {
            refs.appointmentTypeContainer.innerHTML = availableAppointmentTypes.map(function (appointmentType) {
                return '' +
                    '<button class="skds-booking__chip' + (appointmentType.id === state.selectedAppointmentTypeId ? ' is-active' : '') + '" ' +
                    'type="button" data-appointment-type-id="' + appointmentType.id + '">' +
                    escapeHtml(appointmentType.name) +
                    '</button>';
            }).join('');
        }

        function renderServices(availableServices) {
            if (availableServices.length === 0) {
                refs.serviceContainer.innerHTML =
                    '<div class="skds-booking__placeholder">' + escapeHtml(getMessage('noServices', '')) + '</div>';

                return;
            }

            refs.serviceContainer.innerHTML = availableServices.map(function (service) {
                var servicePrice = getAppointmentTypeServicePrices().find(function (row) {
                    return row.serviceId === service.id && getLocationKey(row.locationId) === state.selectedLocationKey;
                });

                if (!servicePrice) {
                    servicePrice = getAppointmentTypeServicePrices().find(function (row) {
                        return row.serviceId === service.id;
                    }) || null;
                }

                var metaParts = [];

                if (servicePrice) {
                    metaParts.push('<strong>' + escapeHtml(formatPrice(servicePrice.price, servicePrice.currency)) + '</strong>');
                    metaParts.push(
                        escapeHtml(getMessage('durationPrefix', ''))
                        + ' '
                        + escapeHtml(String(servicePrice.durationMinutes))
                        + ' '
                        + escapeHtml(getMessage('minutesShort', ''))
                    );
                }

                if (service.description) {
                    metaParts.push(escapeHtml(service.description));
                }

                return '' +
                    '<button class="skds-booking__service' + (service.id === state.selectedServiceId ? ' is-active' : '') + '" ' +
                    'type="button" data-service-id="' + service.id + '">' +
                    '<div class="skds-booking__service-name">' + escapeHtml(service.name) + '</div>' +
                    '<div class="skds-booking__service-meta">' + metaParts.join(' &middot; ') + '</div>' +
                    '</button>';
            }).join('');
        }

        function renderSummary(selectedServicePrice) {
            var selectedDoctor = doctorMap[state.selectedDoctorId] || null;
            var selectedService = serviceMap[state.selectedServiceId] || null;
            var selectedAppointmentType = appointmentTypeMap[state.selectedAppointmentTypeId] || null;

            if (!selectedDoctor || !selectedService || !selectedAppointmentType || !selectedServicePrice) {
                refs.summaryContainer.innerHTML =
                    '<div class="skds-booking__placeholder">' + escapeHtml(getMessage('selectDoctorAndService', '')) + '</div>';

                return;
            }

            refs.summaryContainer.innerHTML = '' +
                '<h3 class="skds-booking__summary-title">' + escapeHtml(selectedDoctor.name) + '</h3>' +
                '<div class="skds-booking__summary-line"><strong>' + escapeHtml(getMessage('formatLabel', '')) + ':</strong> ' + escapeHtml(selectedAppointmentType.name) + '</div>' +
                '<div class="skds-booking__summary-line"><strong>' + escapeHtml(getMessage('serviceLabel', '')) + ':</strong> ' + escapeHtml(selectedService.name) + '</div>' +
                '<div class="skds-booking__summary-line"><strong>' + escapeHtml(getMessage('priceLabel', '')) + ':</strong> ' + escapeHtml(formatPrice(selectedServicePrice.price, selectedServicePrice.currency)) + '</div>' +
                '<div class="skds-booking__summary-line"><strong>' + escapeHtml(getMessage('durationLabel', '')) + ':</strong> ' + escapeHtml(String(selectedServicePrice.durationMinutes)) + ' ' + escapeHtml(getMessage('minutesShort', '')) + '</div>' +
                '<div class="skds-booking__summary-tags">' +
                    getSpecializationNameList(selectedDoctor).map(function (specializationName) {
                        return '<span class="skds-booking__summary-tag">' + escapeHtml(specializationName) + '</span>';
                    }).join('') +
                '</div>';
        }

        function renderDates(availability) {
            if (availability.length === 0) {
                refs.dateContainer.innerHTML =
                    '<div class="skds-booking__placeholder">' + escapeHtml(getMessage('noDates', '')) + '</div>';

                return;
            }

            refs.dateContainer.innerHTML = availability.map(function (dateItem) {
                return '' +
                    '<button class="skds-booking__date' + (dateItem.date === state.selectedDate ? ' is-active' : '') + '" ' +
                    'type="button" data-date-value="' + escapeHtml(dateItem.date) + '">' +
                    '<span class="skds-booking__date-day">' + escapeHtml(dateItem.dayLabel) + '</span>' +
                    '<span class="skds-booking__date-value">' + escapeHtml(dateItem.fullLabel) + '</span>' +
                    '</button>';
            }).join('');
        }

        function renderLocation(availableLocations) {
            if (availableLocations.length === 0) {
                refs.locationContainer.innerHTML =
                    '<div class="skds-booking__placeholder">' + escapeHtml(getMessage('locationAfterService', '')) + '</div>';
                refs.locationNoteContainer.textContent = '';

                return;
            }

            var selectedLocation = availableLocations.find(function (location) {
                return location.key === state.selectedLocationKey;
            }) || availableLocations[0];

            refs.locationNoteContainer.textContent = selectedLocation.address || '';
            refs.locationContainer.innerHTML = availableLocations.map(function (location) {
                return '' +
                    '<button class="skds-booking__location-pill' + (location.key === state.selectedLocationKey ? ' is-active' : '') + '" ' +
                    'type="button" data-location-key="' + escapeHtml(location.key) + '">' +
                    escapeHtml(location.name) +
                    '</button>';
            }).join('');
        }

        function renderSlots(availability) {
            var selectedDateItem = availability.find(function (dateItem) {
                return dateItem.date === state.selectedDate;
            }) || null;

            if (!selectedDateItem) {
                refs.slotContainer.innerHTML =
                    '<div class="skds-booking__placeholder">' + escapeHtml(getMessage('selectDateFirst', '')) + '</div>';

                return;
            }

            refs.slotContainer.innerHTML = selectedDateItem.slots.map(function (slot) {
                return '' +
                    '<button class="skds-booking__slot' + (slot.key === state.selectedSlotKey ? ' is-active' : '') + '" ' +
                    'type="button" data-slot-key="' + escapeHtml(slot.key) + '">' +
                    escapeHtml(formatMinutes(slot.timeFromMinutes)) +
                    '</button>';
            }).join('');
        }

        function renderSelection(prepared) {
            var selectedDoctor = doctorMap[state.selectedDoctorId] || null;
            var selectedService = serviceMap[state.selectedServiceId] || null;
            var bookingIntent = buildBookingIntentDetail(prepared);
            var selectionHint = bookingIntent
                ? getMessage('selectedSlotLabel', '') + ': ' + bookingIntent.bookingDateLabel + ', ' + bookingIntent.slot.label
                : getMessage('chooseTime', '');

            if (!selectedDoctor || !selectedService || !prepared.selectedServicePrice) {
                refs.selectionContainer.innerHTML =
                    '<div class="skds-booking__placeholder">' + escapeHtml(getMessage('componentReady', '')) + '</div>';

                return;
            }

            var lines = [
                '<div class="skds-booking__selection-line"><strong>' + escapeHtml(getMessage('doctorLabel', '')) + ':</strong> ' + escapeHtml(selectedDoctor.name) + '</div>',
                '<div class="skds-booking__selection-line"><strong>' + escapeHtml(getMessage('selectionServiceLabel', '')) + ':</strong> ' + escapeHtml(selectedService.name) + '</div>',
                '<div class="skds-booking__selection-line"><strong>' + escapeHtml(getMessage('selectionPriceLabel', '')) + ':</strong> ' + escapeHtml(formatPrice(prepared.selectedServicePrice.price, prepared.selectedServicePrice.currency)) + '</div>'
            ];

            if (bookingIntent) {
                lines.push(
                    '<div class="skds-booking__selection-line"><strong>' + escapeHtml(getMessage('selectedSlotLabel', '')) + ':</strong> ' +
                    escapeHtml(bookingIntent.bookingDateLabel + ', ' + bookingIntent.slot.label) +
                    '</div>'
                );
            } else {
                lines.push(
                    '<div class="skds-booking__selection-line"><strong>' + escapeHtml(getMessage('slotLabel', '')) + ':</strong> ' +
                    escapeHtml(getMessage('chooseTime', '')) +
                    '</div>'
                );
            }

            refs.selectionContainer.innerHTML =
                '<div class="skds-booking__selection-layout">' +
                    '<div class="skds-booking__selection-copy">' +
                        '<h3 class="skds-booking__selection-title">' + escapeHtml(getMessage('selectionTitle', '')) + '</h3>' +
                        lines.join('') +
                    '</div>' +
                    '<div class="skds-booking__selection-actions">' +
                        '<button class="skds-booking__cta" type="button" data-role="booking-cta"' + (bookingIntent ? '' : ' disabled') + '>' +
                            escapeHtml(getMessage('bookActionLabel', '')) +
                        '</button>' +
                        '<div class="skds-booking__selection-hint">' + escapeHtml(selectionHint) + '</div>' +
                    '</div>' +
                '</div>';
        }

        function renderModal(prepared) {
            if (!refs.modalNode) {
                return;
            }

            if (!runtime.isModalOpen) {
                refs.modalNode.hidden = true;

                return;
            }

            var bookingIntent = buildBookingIntentDetail(prepared);

            if (!bookingIntent) {
                runtime.isModalOpen = false;
                refs.modalNode.hidden = true;

                return;
            }

            refs.modalSummaryNode.innerHTML = '' +
                '<div class="skds-booking__modal-summary-title">' + escapeHtml(getMessage('modalSummaryTitle', '')) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('doctorLabel', '')) + ':</strong> ' + escapeHtml(bookingIntent.doctor.name) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('serviceLabel', '')) + ':</strong> ' + escapeHtml(bookingIntent.service.name) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('formatLabel', '')) + ':</strong> ' + escapeHtml(bookingIntent.appointmentType.name) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('priceLabel', '')) + ':</strong> ' + escapeHtml(formatPrice(bookingIntent.servicePrice.price, bookingIntent.servicePrice.currency)) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('selectedSlotLabel', '')) + ':</strong> ' + escapeHtml(bookingIntent.bookingDateLabel + ', ' + bookingIntent.slot.label) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('locationLabel', '')) + ':</strong> ' + escapeHtml(bookingIntent.location.name) + '</div>';

            refs.patientNameNode.value = runtime.bookingForm.patientName;
            refs.patientPhoneNode.value = runtime.bookingForm.patientPhone;
            refs.patientEmailNode.value = runtime.bookingForm.patientEmail;
            refs.patientCommentNode.value = runtime.bookingForm.patientComment;
            refs.patientConsentNode.checked = runtime.bookingForm.patientConsent;

            refs.patientNameErrorNode.textContent = runtime.bookingErrors.patientName || '';
            refs.patientPhoneErrorNode.textContent = runtime.bookingErrors.patientPhone || '';
            refs.patientEmailErrorNode.textContent = runtime.bookingErrors.patientEmail || '';
            refs.patientConsentErrorNode.textContent = runtime.bookingErrors.patientConsent || '';
            refs.slotErrorNode.textContent = runtime.bookingErrors.slot || '';

            refs.modalFeedbackNode.textContent = runtime.modalMessage;
            refs.modalFeedbackNode.hidden = runtime.modalMessage === '';
            refs.modalFeedbackNode.className = 'skds-booking__modal-feedback' + (runtime.modalMessage !== '' ? ' is-' + runtime.modalMessageType : '');

            refs.modalSubmitNode.textContent = runtime.isSubmitting
                ? getMessage('modalSubmitLoading', '')
                : getMessage('modalSubmit', '');
            refs.modalSubmitNode.disabled = runtime.isSubmitting;

            refs.modalNode.hidden = false;
        }

        function renderSuccessModal() {
            if (!refs.successModalNode) {
                return;
            }

            if (!runtime.isSuccessModalOpen || !runtime.successBookingIntent) {
                refs.successModalNode.hidden = true;

                return;
            }

            refs.successModalMessageNode.textContent = runtime.successMessage;
            refs.successModalSummaryNode.innerHTML = '' +
                '<div class="skds-booking__modal-summary-title">' + escapeHtml(getMessage('modalSummaryTitle', '')) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('doctorLabel', '')) + ':</strong> ' + escapeHtml(runtime.successBookingIntent.doctor.name) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('serviceLabel', '')) + ':</strong> ' + escapeHtml(runtime.successBookingIntent.service.name) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('formatLabel', '')) + ':</strong> ' + escapeHtml(runtime.successBookingIntent.appointmentType.name) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('priceLabel', '')) + ':</strong> ' + escapeHtml(formatPrice(runtime.successBookingIntent.servicePrice.price, runtime.successBookingIntent.servicePrice.currency)) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('selectedSlotLabel', '')) + ':</strong> ' + escapeHtml(runtime.successBookingIntent.bookingDateLabel + ', ' + runtime.successBookingIntent.slot.label) + '</div>' +
                '<div class="skds-booking__modal-summary-line"><strong>' + escapeHtml(getMessage('locationLabel', '')) + ':</strong> ' + escapeHtml(runtime.successBookingIntent.location.name) + '</div>';

            refs.successModalNode.hidden = false;
        }

        function renderConsentModal() {
            if (!refs.consentModalNode) {
                return;
            }

            refs.consentModalNode.hidden = !runtime.isConsentModalOpen;
        }

        function syncBodyModalState() {
            document.body.classList.toggle(
                'skds-booking-modal-open',
                runtime.isModalOpen || runtime.isSuccessModalOpen || runtime.isConsentModalOpen
            );
        }

        function render(preparedState) {
            var prepared = preparedState || syncState();

            renderSpecializations(prepared.usedSpecializations);
            renderDoctors(prepared.availableDoctors);
            renderAppointmentTypes(prepared.availableAppointmentTypes);
            renderServices(prepared.availableServices);
            renderSummary(prepared.selectedServicePrice);
            renderDates(prepared.availability);
            renderLocation(prepared.availableLocations);
            renderSlots(prepared.availability);
            renderSelection(prepared);
            renderModal(prepared);
            renderSuccessModal();
            renderConsentModal();
            syncBodyModalState();
        }

        function openModal() {
            clearModalErrors();
            runtime.isSuccessModalOpen = false;
            runtime.isConsentModalOpen = false;
            runtime.successBookingIntent = null;
            runtime.successMessage = '';
            runtime.isModalOpen = true;
            render();
        }

        function closeModal() {
            runtime.isModalOpen = false;
            runtime.isConsentModalOpen = false;
            runtime.isSubmitting = false;
            clearModalErrors();
            render();
        }

        function closeSuccessModal() {
            runtime.isSuccessModalOpen = false;
            runtime.isConsentModalOpen = false;
            runtime.successBookingIntent = null;
            runtime.successMessage = '';
            resetBookingForm();
            clearModalErrors();
            render();
        }

        function openConsentModal() {
            runtime.isConsentModalOpen = true;
            render();
        }

        function closeConsentModal() {
            runtime.isConsentModalOpen = false;
            render();
        }

        function validateBookingForm() {
            var errors = {};
            var phoneDigits = getPhoneDigits(runtime.bookingForm.patientPhone);

            if (runtime.bookingForm.patientName.trim() === '') {
                errors.patientName = getMessage('modalPatientName', '') + ' ' + getMessage('validationRequiredSuffix', '');
            }

            if (runtime.bookingForm.patientPhone.trim() === '') {
                errors.patientPhone = getMessage('modalPatientPhone', '') + ' ' + getMessage('validationRequiredSuffix', '');
            } else if (phoneDigits.length < 11) {
                errors.patientPhone = getMessage('validationPhoneInvalid', '');
            }

            if (
                runtime.bookingForm.patientEmail.trim() !== ''
                && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(runtime.bookingForm.patientEmail.trim())
            ) {
                errors.patientEmail = getMessage('validationEmailInvalid', '');
            }

            if (!runtime.bookingForm.patientConsent) {
                errors.patientConsent = getMessage('validationConsentRequired', '');
            }

            return errors;
        }

        function buildRequestData(bookingIntent) {
            var requestData = new URLSearchParams();

            requestData.set('sklyar_ds_action', getMessage('ajaxAction', 'create_booking'));
            requestData.set('sessid', getMessage('sessionId', ''));
            requestData.set('service_price_id', String(bookingIntent.servicePrice.id));
            requestData.set('doctor_id', String(bookingIntent.doctor.id));
            requestData.set('service_id', String(bookingIntent.service.id));
            requestData.set('appointment_type_id', String(bookingIntent.appointmentType.id));
            requestData.set('location_id', bookingIntent.location.id === null ? '' : String(bookingIntent.location.id));
            requestData.set('booking_date', bookingIntent.bookingDate);
            requestData.set('time_from_minutes', String(bookingIntent.slot.timeFromMinutes));
            requestData.set('time_to_minutes', String(bookingIntent.slot.timeToMinutes));
            requestData.set('patient_name', runtime.bookingForm.patientName.trim());
            requestData.set('patient_phone', runtime.bookingForm.patientPhone.trim());
            requestData.set('patient_email', runtime.bookingForm.patientEmail.trim());
            requestData.set('patient_comment', runtime.bookingForm.patientComment.trim());
            requestData.set('patient_consent', runtime.bookingForm.patientConsent ? 'Y' : 'N');

            return requestData;
        }

        function submitBooking() {
            var prepared = syncState();
            var bookingIntent = buildBookingIntentDetail(prepared);

            if (!bookingIntent) {
                runtime.bookingErrors = {
                    slot: getMessage('chooseTime', '')
                };
                render(prepared);

                return;
            }

            runtime.bookingErrors = validateBookingForm();

            if (Object.keys(runtime.bookingErrors).length > 0) {
                runtime.modalMessage = getMessage('validationFormInvalid', '');
                runtime.modalMessageType = 'error';
                render(prepared);

                return;
            }

            runtime.isSubmitting = true;
            runtime.modalMessage = '';
            render(prepared);

            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: buildRequestData(bookingIntent).toString()
            }).then(function (response) {
                return response.json();
            }).then(function (response) {
                runtime.isSubmitting = false;

                if (!response || response.success !== true) {
                    runtime.modalMessage = response && response.message
                        ? response.message
                        : getMessage('modalUnexpectedError', '');
                    runtime.modalMessageType = 'error';
                    runtime.bookingErrors = response && response.errors ? response.errors : {};
                    render();

                    return;
                }

                if (response.booking) {
                    payload.bookings.push(response.booking);
                }

                runtime.isModalOpen = false;
                runtime.isConsentModalOpen = false;
                runtime.isSuccessModalOpen = true;
                runtime.successBookingIntent = bookingIntent;
                runtime.successMessage = response.message || '';
                runtime.modalMessage = '';
                runtime.modalMessageType = 'error';
                runtime.bookingErrors = {};
                state.selectedSlotKey = null;
                render();
            }).catch(function () {
                runtime.isSubmitting = false;
                runtime.modalMessage = getMessage('modalUnexpectedError', '');
                runtime.modalMessageType = 'error';
                render();
            });
        }

        rootNode.addEventListener('click', function (event) {
            var specializationButton = event.target.closest('[data-specialization-id]');
            var doctorButton = event.target.closest('[data-doctor-id]');
            var appointmentTypeButton = event.target.closest('[data-appointment-type-id]');
            var serviceButton = event.target.closest('[data-service-id]');
            var locationButton = event.target.closest('[data-location-key]');
            var dateButton = event.target.closest('[data-date-value]');
            var slotButton = event.target.closest('[data-slot-key]');
            var bookingButton = event.target.closest('[data-role="booking-cta"]');
            var consentOpenButton = event.target.closest('[data-role="consent-open"]');
            var modalCloseButton = event.target.closest('[data-role="modal-close"]');
            var successModalCloseButton = event.target.closest('[data-role="success-modal-close"]');
            var consentModalCloseButton = event.target.closest('[data-role="consent-modal-close"]');

            if (modalCloseButton) {
                closeModal();

                return;
            }

            if (successModalCloseButton) {
                closeSuccessModal();

                return;
            }

            if (consentModalCloseButton) {
                closeConsentModal();

                return;
            }

            if (consentOpenButton) {
                openConsentModal();

                return;
            }

            if (specializationButton) {
                clearFlashMessage();
                state.selectedSpecializationId = Number(specializationButton.getAttribute('data-specialization-id'));
                state.selectedDoctorId = null;
                state.selectedAppointmentTypeId = null;
                state.selectedServiceId = null;
                state.selectedLocationKey = null;
                state.selectedDate = null;
                state.selectedSlotKey = null;
                render();

                return;
            }

            if (doctorButton) {
                clearFlashMessage();
                state.selectedDoctorId = Number(doctorButton.getAttribute('data-doctor-id'));
                state.selectedAppointmentTypeId = null;
                state.selectedServiceId = null;
                state.selectedLocationKey = null;
                state.selectedDate = null;
                state.selectedSlotKey = null;
                render();

                return;
            }

            if (appointmentTypeButton) {
                clearFlashMessage();
                state.selectedAppointmentTypeId = Number(appointmentTypeButton.getAttribute('data-appointment-type-id'));
                state.selectedServiceId = null;
                state.selectedLocationKey = null;
                state.selectedDate = null;
                state.selectedSlotKey = null;
                render();

                return;
            }

            if (serviceButton) {
                clearFlashMessage();
                state.selectedServiceId = Number(serviceButton.getAttribute('data-service-id'));
                state.selectedLocationKey = null;
                state.selectedDate = null;
                state.selectedSlotKey = null;
                render();

                return;
            }

            if (locationButton) {
                clearFlashMessage();
                state.selectedLocationKey = locationButton.getAttribute('data-location-key');
                state.selectedDate = null;
                state.selectedSlotKey = null;
                render();

                return;
            }

            if (dateButton) {
                clearFlashMessage();
                state.selectedDate = dateButton.getAttribute('data-date-value');
                state.selectedSlotKey = null;
                render();

                return;
            }

            if (slotButton) {
                clearFlashMessage();
                state.selectedSlotKey = slotButton.getAttribute('data-slot-key');
                render();

                return;
            }

            if (bookingButton) {
                openModal();
            }
        });

        if (refs.bookingFormNode) {
            refs.bookingFormNode.addEventListener('submit', function (event) {
                event.preventDefault();
                submitBooking();
            });

            refs.bookingFormNode.addEventListener('input', function (event) {
                var target = event.target;

                if (target === refs.patientNameNode) {
                    runtime.bookingForm.patientName = target.value;
                    delete runtime.bookingErrors.patientName;
                } else if (target === refs.patientPhoneNode) {
                    runtime.bookingForm.patientPhone = formatPhoneValue(target.value);
                    target.value = runtime.bookingForm.patientPhone;
                    delete runtime.bookingErrors.patientPhone;
                } else if (target === refs.patientEmailNode) {
                    runtime.bookingForm.patientEmail = target.value;
                    delete runtime.bookingErrors.patientEmail;
                } else if (target === refs.patientConsentNode) {
                    runtime.bookingForm.patientConsent = target.checked;
                    delete runtime.bookingErrors.patientConsent;
                } else if (target === refs.patientCommentNode) {
                    runtime.bookingForm.patientComment = target.value;
                }

                runtime.modalMessage = '';
                render();
            });

            refs.bookingFormNode.addEventListener('change', function (event) {
                var target = event.target;

                if (target === refs.patientConsentNode) {
                    runtime.bookingForm.patientConsent = target.checked;
                    delete runtime.bookingErrors.patientConsent;
                    runtime.modalMessage = '';
                    render();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && runtime.isConsentModalOpen) {
                closeConsentModal();
            } else if (event.key === 'Escape' && runtime.isSuccessModalOpen) {
                closeSuccessModal();
            } else if (event.key === 'Escape' && runtime.isModalOpen) {
                closeModal();
            }
        });

        render();
    }

    function bootstrapBookingComponents() {
        document.querySelectorAll('.js-skds-booking').forEach(initBookingComponent);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapBookingComponents);
    } else {
        bootstrapBookingComponents();
    }
}());
