import { components, element, html, i18n } from '../utils/index.js';

const { Button } = components;
const { Fragment } = element;

const { __ } = i18n;

function SolutionRequiredPackages(props ) {
	const {
		requiredPackages,
	} = props;

	const requiredButtons = requiredPackages.map( ( requiredPackage, index ) => {

		let className = 'button pressody_retailer-required-package';

		return html`
			<${ Button }
				key=${ requiredPackage.displayName }
				className=${ className }
				href=${ requiredPackage.editLink }
			>
				${ requiredPackage.displayName }
			</${ Button }>
			${ ' ' }
		`;
	} );

	return html`
		<${ Fragment }>
			${ requiredButtons.length ? requiredButtons : __( 'None', 'pressody_retailer' ) }
		</${ Fragment }
	`;
}

export default SolutionRequiredPackages;
