import htm from '../vendor/htm.module.js';

export const { components, data, dataControls, element, i18n, url } = wp;

export const html = htm.bind( React.createElement );

export const noop = () => {};
