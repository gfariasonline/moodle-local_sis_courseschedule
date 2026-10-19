// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Weekly grid to select the allowed access intervals of a course.
 *
 * Days are columns and half hours are rows. Dragging over free cells allows them, dragging
 * starting on an allowed cell removes them. The list below the grid edits intervals to the minute
 * and is the keyboard alternative. The model is serialised into the hidden slotsjson field.
 *
 * @module     local_sis_courseschedule/schedule_grid
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const STEP = 30;
const ROWS = 1440 / STEP;
const DAYS = [1, 2, 3, 4, 5, 6, 7];

/**
 * Minutes of the day as HH:MM.
 *
 * @param {Number} minute
 * @returns {String}
 */
const formatMinute = (minute) => {
    const hours = Math.floor(minute / 60);
    const rest = minute % 60;
    return String(hours).padStart(2, '0') + ':' + String(rest).padStart(2, '0');
};

/**
 * Parse HH:MM into minutes of the day.
 *
 * @param {String} value
 * @param {Boolean} isEnd Whether 24:00 is accepted.
 * @returns {Number|null}
 */
const parseTime = (value, isEnd) => {
    const match = /^([01]?\d|2[0-4]):([0-5]\d)$/.exec(value.trim());
    if (!match) {
        return null;
    }
    const minute = Number(match[1]) * 60 + Number(match[2]);
    if (minute > 1440 || (minute === 1440 && !isEnd)) {
        return null;
    }
    return minute;
};

/**
 * Sort and merge overlapping or adjacent intervals of the same day.
 *
 * @param {Object[]} slots Objects with weekday, startminute and endminute.
 * @returns {Object[]}
 */
const normalise = (slots) => {
    const sorted = slots
        .filter((slot) => slot.endminute > slot.startminute)
        .map((slot) => ({...slot}))
        .sort((a, b) => a.weekday - b.weekday || a.startminute - b.startminute);
    const merged = [];
    sorted.forEach((slot) => {
        const last = merged[merged.length - 1];
        if (last && last.weekday === slot.weekday && slot.startminute <= last.endminute) {
            last.endminute = Math.max(last.endminute, slot.endminute);
        } else {
            merged.push(slot);
        }
    });
    return merged;
};

/**
 * Remove a range of one day from the intervals.
 *
 * @param {Object[]} slots
 * @param {Number} day
 * @param {Number} start
 * @param {Number} end
 * @returns {Object[]}
 */
const subtract = (slots, day, start, end) => {
    const result = [];
    slots.forEach((slot) => {
        if (slot.weekday !== day || slot.endminute <= start || slot.startminute >= end) {
            result.push(slot);
            return;
        }
        if (slot.startminute < start) {
            result.push({weekday: day, startminute: slot.startminute, endminute: start});
        }
        if (slot.endminute > end) {
            result.push({weekday: day, startminute: end, endminute: slot.endminute});
        }
    });
    return result;
};

/**
 * Create an element.
 *
 * @param {String} tag
 * @param {Object} attributes
 * @param {String} [text]
 * @returns {HTMLElement}
 */
const element = (tag, attributes = {}, text = '') => {
    const node = document.createElement(tag);
    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
    if (text !== '') {
        node.textContent = text;
    }
    return node;
};

/**
 * Initialise the grid.
 *
 * @param {Object} config Keys: selector (mount point), field (hidden input name), strings (labels), days (labels by number).
 */
export const init = (config) => {
    const root = document.querySelector(config.selector);
    const field = document.querySelector('input[name="' + config.field + '"]');
    if (!root || !field) {
        return;
    }
    const strings = config.strings;
    let slots = [];
    try {
        slots = normalise(JSON.parse(field.value || '[]'));
    } catch (e) {
        slots = [];
    }

    const toolbar = element('div', {class: 'lsc-toolbar'});
    const copyButton = element('button', {type: 'button', class: 'btn btn-secondary btn-sm'}, strings.copyweekdays);
    const clearButton = element('button', {type: 'button', class: 'btn btn-secondary btn-sm'}, strings.clearweek);
    toolbar.append(copyButton, clearButton);

    const scroller = element('div', {class: 'lsc-scroller'});
    const grid = element('div', {class: 'lsc-grid', role: 'presentation'});
    scroller.append(grid);
    const cells = {};

    grid.append(element('div', {class: 'lsc-corner'}));
    DAYS.forEach((day) => grid.append(element('div', {class: 'lsc-dayhead'}, config.days[day])));
    for (let row = 0; row < ROWS; row++) {
        const label = element('div', {class: 'lsc-time'}, row % 2 === 0 ? formatMinute(row * STEP) : '');
        grid.append(label);
        DAYS.forEach((day) => {
            const cell = element('div', {class: 'lsc-cell' + (row % 2 === 0 ? ' lsc-hour' : '')});
            cell.dataset.day = day;
            cell.dataset.row = row;
            cells[day + '-' + row] = cell;
            grid.append(cell);
        });
    }

    const listTitle = element('h4', {class: 'lsc-listtitle'}, strings.listtitle);
    const list = element('div', {class: 'lsc-list'});
    const adder = element('div', {class: 'lsc-adder'});
    const daySelect = element('select', {class: 'custom-select', 'aria-label': strings.weekday});
    DAYS.forEach((day) => daySelect.append(element('option', {value: day}, config.days[day])));
    const addButton = element('button', {type: 'button', class: 'btn btn-secondary btn-sm'}, strings.addperiod);
    adder.append(daySelect, addButton);

    root.replaceChildren(
        element('p', {class: 'lsc-help text-muted'}, strings.help), toolbar, scroller, listTitle, list, adder
    );

    const paint = (preview) => {
        const on = new Set();
        slots.forEach((slot) => {
            for (let row = slot.startminute / STEP; row < slot.endminute / STEP; row++) {
                on.add(slot.weekday + '-' + Math.floor(row));
            }
        });
        Object.entries(cells).forEach(([key, cell]) => {
            let allowed = on.has(key);
            if (preview && preview.keys.has(key)) {
                allowed = preview.add;
                cell.classList.add('lsc-preview');
            } else {
                cell.classList.remove('lsc-preview');
            }
            cell.classList.toggle('lsc-on', allowed);
        });
    };

    const renderList = () => {
        list.replaceChildren();
        if (!slots.length) {
            list.append(element('p', {class: 'text-muted'}, strings.empty));
            return;
        }
        slots.forEach((slot, index) => {
            const row = element('div', {class: 'lsc-row'});
            row.append(element('span', {class: 'lsc-rowday'}, config.days[slot.weekday]));
            const dayLabel = config.days[slot.weekday];
            const start = element('input', {
                type: 'text', size: 5, class: 'form-control form-control-sm',
                'aria-label': strings.starttime + ', ' + dayLabel, value: formatMinute(slot.startminute),
            });
            const end = element('input', {
                type: 'text', size: 5, class: 'form-control form-control-sm',
                'aria-label': strings.endtime + ', ' + dayLabel, value: formatMinute(slot.endminute),
            });
            const remove = element('button', {
                type: 'button', class: 'btn btn-link btn-sm', 'aria-label': strings.remove + ' ' + config.days[slot.weekday] +
                    ' ' + formatMinute(slot.startminute) + '-' + formatMinute(slot.endminute),
            }, strings.remove);
            const update = () => {
                const startMinute = parseTime(start.value, false);
                const endMinute = parseTime(end.value, true);
                const valid = startMinute !== null && endMinute !== null && endMinute > startMinute;
                start.setAttribute('aria-invalid', valid ? 'false' : 'true');
                end.setAttribute('aria-invalid', valid ? 'false' : 'true');
                start.classList.toggle('is-invalid', !valid);
                end.classList.toggle('is-invalid', !valid);
                if (!valid) {
                    return;
                }
                slots[index] = {weekday: slot.weekday, startminute: startMinute, endminute: endMinute};
                commit();
            };
            start.addEventListener('change', update);
            end.addEventListener('change', update);
            remove.addEventListener('click', () => {
                slots.splice(index, 1);
                commit();
            });
            row.append(start, element('span', {class: 'lsc-sep', 'aria-hidden': 'true'}, '–'), end, remove);
            list.append(row);
        });
    };

    /**
     * Normalise the intervals, store them in the hidden field and redraw.
     */
    function commit() {
        slots = normalise(slots);
        field.value = JSON.stringify(slots);
        paint();
        renderList();
    }

    // Drag selection.
    let drag = null;
    const cellFromPoint = (x, y) => {
        const target = document.elementFromPoint(x, y);
        return target && target.classList.contains('lsc-cell') ? target : null;
    };
    const dragRange = (cell) => {
        const row = Number(cell.dataset.row);
        const from = Math.min(drag.row, row);
        const to = Math.max(drag.row, row);
        const keys = new Set();
        for (let i = from; i <= to; i++) {
            keys.add(drag.day + '-' + i);
        }
        return {from, to, keys, add: drag.add};
    };
    grid.addEventListener('pointerdown', (event) => {
        const cell = event.target.closest('.lsc-cell');
        if (!cell || (event.pointerType === 'mouse' && event.button !== 0)) {
            return;
        }
        drag = {day: Number(cell.dataset.day), row: Number(cell.dataset.row), add: !cell.classList.contains('lsc-on')};
        drag.current = dragRange(cell);
        paint(drag.current);
        if (event.pointerType === 'mouse') {
            event.preventDefault();
        }
    });
    grid.addEventListener('pointermove', (event) => {
        if (!drag) {
            return;
        }
        const cell = cellFromPoint(event.clientX, event.clientY);
        if (cell && Number(cell.dataset.day) === drag.day) {
            drag.current = dragRange(cell);
            paint(drag.current);
        }
    });
    const finish = (apply) => {
        if (!drag) {
            return;
        }
        const range = drag.current;
        const day = drag.day;
        drag = null;
        if (!apply) {
            paint();
            return;
        }
        const start = range.from * STEP;
        const end = (range.to + 1) * STEP;
        slots = range.add ? slots.concat([{weekday: day, startminute: start, endminute: end}]) : subtract(slots, day, start, end);
        commit();
    };
    document.addEventListener('pointerup', () => finish(true));
    document.addEventListener('pointercancel', () => finish(false));

    addButton.addEventListener('click', () => {
        const day = Number(daySelect.value);
        slots.push({weekday: day, startminute: 8 * 60, endminute: 12 * 60});
        commit();
    });
    clearButton.addEventListener('click', () => {
        slots = [];
        commit();
    });
    copyButton.addEventListener('click', () => {
        const monday = slots.filter((slot) => slot.weekday === 1);
        slots = slots.filter((slot) => slot.weekday === 1 || slot.weekday > 5);
        [2, 3, 4, 5].forEach((day) => monday.forEach((slot) => slots.push({...slot, weekday: day})));
        commit();
    });

    commit();
};
