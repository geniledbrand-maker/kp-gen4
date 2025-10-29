const test = require('node:test');
const assert = require('node:assert/strict');

const {
    resolvePricingBase,
    calculateDiscountPercent,
} = require('../assets/js/price-math.js');

test('uses existing base when present', () => {
    const result = resolvePricingBase({
        currentBase: 50000,
        currentMic: 0,
        fetchedMic: 61000,
        newPriceTypeId: 3,
        fallbackPrice: 31000,
    });

    assert.equal(result.baseToUse, 50000);
    assert.equal(result.baseToStore, 50000);
    assert.equal(result.micToStore, 61000);

    const discount = calculateDiscountPercent(result.baseToUse, 31000);
    assert.ok(discount > 37.9 && discount < 38.1);
});

test('falls back to fetched mic when base missing', () => {
    const result = resolvePricingBase({
        currentBase: 0,
        currentMic: 0,
        fetchedMic: 60000,
        newPriceTypeId: 3,
        fallbackPrice: 31200,
    });

    assert.equal(result.baseToUse, 60000);
    assert.equal(result.baseToStore, 60000);

    const discount = calculateDiscountPercent(result.baseToUse, 31200);
    assert.ok(discount > 47.9 && discount < 48.1);
});

test('refreshing to МИЦ updates base storage', () => {
    const result = resolvePricingBase({
        currentBase: 51000,
        currentMic: 50000,
        fetchedMic: 50500,
        newPriceTypeId: 2,
        fallbackPrice: 50500,
    });

    assert.equal(result.baseToUse, 50500);
    assert.equal(result.baseToStore, 50500);
    assert.equal(result.micToStore, 50500);
});

test('uses fallback price when nothing else available', () => {
    const result = resolvePricingBase({
        currentBase: 0,
        currentMic: 0,
        fetchedMic: 0,
        newPriceTypeId: 5,
        fallbackPrice: 25000,
    });

    assert.equal(result.baseToUse, 25000);

    const discount = calculateDiscountPercent(result.baseToUse, 25000);
    assert.equal(discount, 0);
});
