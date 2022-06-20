import { components, element, html, i18n } from '../utils/index.js';

const { Button, Flex, FlexItem, TextControl } = components;
const { useState } = element;
const { __ } = i18n;

function ApiKeyForm( props ) {
	const { onSubmit } = props;

	const [ name, setName ] = useState( '' );

	const isEmpty = '' === name;

	const onClick = () => {
		onSubmit( name );
		setName( '' );
	};

	return html`
		<${ Flex } justify="start">
			<${ FlexItem }>
				<${ TextControl }
					label=${ __( 'API Key Name', 'pressody_retailer' ) }
					hideLabelFromVision
					placeholder=${ __( 'Name', 'pressody_retailer' ) }
					onChange=${ setName }
					value=${ name }
				/>
			</${ FlexItem }>
			<${ FlexItem }>
				<${ Button }
					isPrimary=${ ! isEmpty }
					isSecondary=${ isEmpty }
					disabled="${ isEmpty && 'disabled' }"
					onClick=${ onClick }
				>
					${ __( 'Create API Key', 'pressody_retailer' ) }
				</${ Button }>
			</${ FlexItem }>
		</${ Flex }>
	`;
};

export default ApiKeyForm;
