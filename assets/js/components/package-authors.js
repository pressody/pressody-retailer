import { element, html, i18n } from '../utils/index.js';

const { Fragment } = element;

const { __ } = i18n;

function PackageAuthors( props ) {
	const {
		authors,
	} = props;

	const authorLinks = authors.map( ( author, index ) => {

		let className = 'package-author';

		return html`
			<a
				key=${ author.name }
				className=${ className }
				href=${ author.homepage ? author.homepage : '#' }
				target="_blank"
				rel="noopener noreferer"
			>
				${ author.name }
			</a>
		`;
	} );

	return html`
		<${ Fragment }>
			${ authorLinks.length ? authorLinks : __( 'None', 'pressody_retailer' ) }
		</${ Fragment }
	`;
}

export default PackageAuthors;
