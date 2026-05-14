import * as yup from 'yup';

const phonePattern = /^[0-9+\-() ]{7,30}$/;

function todayDate() {
    return new Date(new Date().toDateString());
}

function roundTimeUpToFiveMinutes(time) {
    const [hours, minutes] = time.split(':').map(Number);
    const roundedMinutes = Math.ceil(((hours * 60) + minutes) / 5) * 5;
    const boundedMinutes = Math.min(roundedMinutes, (23 * 60) + 55);

    return `${String(Math.floor(boundedMinutes / 60)).padStart(2, '0')}:${String(boundedMinutes % 60).padStart(2, '0')}`;
}

function currentLocalDateString() {
    const today = new Date();
    const timezoneOffsetInMilliseconds = today.getTimezoneOffset() * 60 * 1000;

    return new Date(today.getTime() - timezoneOffsetInMilliseconds).toISOString().slice(0, 10);
}

function currentLocalLatestBookingDateString() {
    const today = new Date();
    const localToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const targetYear = localToday.getFullYear();
    const targetMonth = localToday.getMonth() + 1;
    const lastDayOfTargetMonth = new Date(targetYear, targetMonth + 1, 0).getDate();
    const targetDay = Math.min(localToday.getDate(), lastDayOfTargetMonth);
    const latestBookingDate = new Date(targetYear, targetMonth, targetDay);
    const timezoneOffsetInMilliseconds = latestBookingDate.getTimezoneOffset() * 60 * 1000;

    return new Date(latestBookingDate.getTime() - timezoneOffsetInMilliseconds).toISOString().slice(0, 10);
}

function currentRoundedTimeString() {
    const now = new Date();

    return roundTimeUpToFiveMinutes(`${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`);
}

function optionalTrimmedString(maxLength) {
    return yup
        .string()
        .transform((value) => value?.trim() || '')
        .max(maxLength)
        .nullable();
}

function requiredTrimmedString(label, maxLength) {
    return yup
        .string()
        .transform((value) => value?.trim() || '')
        .required(`${label} is required.`)
        .max(maxLength, `${label} must be at most ${maxLength} characters.`);
}

function requiredNumber(label) {
    return yup
        .number()
        .transform((value, originalValue) => (originalValue === '' || originalValue === null ? null : value))
        .typeError(`${label} is required.`)
        .required(`${label} is required.`);
}

function futureOrTodayDate(label) {
    return yup
        .string()
        .required(`${label} is required.`)
        .test('future-or-today', 'Please choose today or a future date.', (value) => {
            if (! value) {
                return false;
            }

            return new Date(`${value}T00:00:00`) >= todayDate();
        });
}

function bookingWindowDate(label) {
    return futureOrTodayDate(label)
        .test('within-one-month', `${label} must be within one month from today.`, (value) => {
            if (! value) {
                return false;
            }

            return value <= currentLocalLatestBookingDateString();
        });
}

function currentOrFutureTime(dateField) {
    return yup
        .string()
        .required('Start time is required.')
        .test('current-or-future-time', 'Please choose the current time or a future time.', function validateTime(value) {
            if (! value) {
                return false;
            }

            const selectedDate = this.parent?.[dateField];

            if (selectedDate !== currentLocalDateString()) {
                return true;
            }

            return value >= currentRoundedTimeString();
        });
}

export {
    bookingWindowDate,
    currentOrFutureTime,
    currentLocalLatestBookingDateString,
    futureOrTodayDate,
    optionalTrimmedString,
    phonePattern,
    requiredNumber,
    requiredTrimmedString,
    yup,
};
