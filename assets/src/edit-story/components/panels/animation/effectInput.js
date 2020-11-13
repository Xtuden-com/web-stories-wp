/*
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * External dependencies
 */ import { useCallback } from 'react';

import PropTypes from 'prop-types';

/**
 * Internal dependencies
 */
import {
  DIRECTION,
  FIELD_TYPES,
  ROTATION,
} from '../../../../animation/constants';
import { GeneralAnimationPropTypes } from '../../../../animation/outputs/types';
import { AnimationFormPropTypes } from '../../../../animation/types';
import { DropDown, BoxedNumeric } from '../../form';
import { DirectionRadioInput } from './directionRadioInput';

function EffectInput({ effectProps, effectConfig, field, onChange }) {
  const directionControlOnChange = useCallback(
    ({ nativeEvent: { target } }) => onChange(target.value, true),
    [onChange]
  );
  switch (effectProps[field].type) {
    case FIELD_TYPES.DROPDOWN:
      return (
        <DropDown
          value={effectConfig[field] || effectProps[field].defaultValue}
          onChange={(value) => onChange(value, true)}
          options={effectProps[field].values.map((v) => ({
            value: v,
            name: v,
          }))}
        />
      );
    case FIELD_TYPES.DIRECTION_PICKER:
      return (
        <DirectionRadioInput
          directions={Object.values(DIRECTION)}
          defaultChecked={
            effectConfig[field] || effectProps[field].defaultValue
          }
          onChange={directionControlOnChange}
        />
      );
    case FIELD_TYPES.ROTATION_PICKER:
      return (
        <DirectionRadioInput
          directions={[DIRECTION.LEFT_TO_RIGHT, DIRECTION.RIGHT_TO_LEFT]}
          defaultChecked={
            effectConfig[field] || effectProps[field].defaultValue
          }
          onChange={directionControlOnChange}
        />
      );
    default:
      return (
        <BoxedNumeric
          aria-label={effectProps[field].label}
          suffix={effectProps[field].label}
          symbol={effectProps[field].unit}
          value={effectConfig[field] || effectProps[field].defaultValue}
          min={0}
          onChange={onChange}
          canBeNegative={false}
          float={effectProps[field].type === FIELD_TYPES.FLOAT}
          flexBasis={'100%'}
        />
      );
  }
}

EffectInput.propTypes = {
  effectProps: AnimationFormPropTypes.isRequired,
  effectConfig: PropTypes.shape(GeneralAnimationPropTypes).isRequired,
  field: PropTypes.string.isRequired,
  onChange: PropTypes.func.isRequired,
};

export default EffectInput;
