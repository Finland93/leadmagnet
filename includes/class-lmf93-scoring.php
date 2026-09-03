<?php
/**
 * Configurable lead scoring and lead-value (pricing) calculation.
 *
 * Rules live in the form config, so scoring is not hard-coded to any industry.
 *
 * Scoring rule shape:
 *   [ 'field' => 'service_type', 'operator' => 'equals', 'value' => 'fault', 'points' => 30 ]
 *
 * Value rule shape (per-lead pricing):
 *   [
 *     'field' => 'service_type', 'operator' => 'equals', 'value' => 'cleaning',
 *     'base_price' => 30,
 *     'multiplier_field' => 'unit_count',   // optional
 *     'multiplier_step'  => 0.25            // +25% per extra unit
 *   ]
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Scoring
 */
class LMF93_Scoring {

	/**
	 * Compute a lead score from rules.
	 *
	 * @param array $config Form config.
	 * @param array $fields Submitted values.
	 * @return int
	 */
	public static function score( $config, $fields ) {
		$rules = isset( $config['scoring'] ) ? $config['scoring'] : array();
		$score = 0;

		foreach ( $rules as $rule ) {
			if ( self::matches( $rule, $fields ) ) {
				$score += isset( $rule['points'] ) ? (int) $rule['points'] : 0;
			}
		}

		return (int) apply_filters( 'lmf93_lead_score', $score, $config, $fields );
	}

	/**
	 * Compute a monetary lead value (partner billing price).
	 *
	 * The first matching value rule wins. Base price can be scaled by a
	 * "units" style field: price = base * (1 + step * (units - 1)).
	 *
	 * @param array $config Form config.
	 * @param array $fields Submitted values.
	 * @return float
	 */
	public static function value( $config, $fields ) {
		$rules = isset( $config['value_rules'] ) ? $config['value_rules'] : array();
		$value = 0.0;

		foreach ( $rules as $rule ) {
			if ( ! self::matches( $rule, $fields ) ) {
				continue;
			}

			$base = isset( $rule['base_price'] ) ? (float) $rule['base_price'] : 0.0;

			// Optional multiplier by a units field.
			if ( ! empty( $rule['multiplier_field'] ) ) {
				$mfield = $rule['multiplier_field'];
				$step   = isset( $rule['multiplier_step'] ) ? (float) $rule['multiplier_step'] : 0.0;
				$units  = isset( $fields[ $mfield ] ) ? self::to_units( $fields[ $mfield ] ) : 1;
				if ( $units < 1 ) {
					$units = 1;
				}
				$base = $base * ( 1 + $step * ( $units - 1 ) );
			}

			$value = round( $base, 2 );
			break; // First match wins.
		}

		return (float) apply_filters( 'lmf93_lead_value', $value, $config, $fields );
	}

	/**
	 * Interpret a field value as a unit count (handles "3+", "2 pcs", etc.).
	 *
	 * @param mixed $raw Raw value.
	 * @return int
	 */
	protected static function to_units( $raw ) {
		if ( is_array( $raw ) ) {
			$raw = reset( $raw );
		}
		if ( preg_match( '/(\d+)/', (string) $raw, $m ) ) {
			return (int) $m[1];
		}
		return 1;
	}

	/**
	 * Evaluate a single rule's condition against submitted fields.
	 *
	 * @param array $rule   Rule.
	 * @param array $fields Fields.
	 * @return bool
	 */
	protected static function matches( $rule, $fields ) {
		// Multi-condition rules: every condition in 'conditions' must match (AND).
		// Shape: 'conditions' => [ ['field'=>..,'operator'=>..,'value'=>..], ... ]
		if ( ! empty( $rule['conditions'] ) && is_array( $rule['conditions'] ) ) {
			foreach ( $rule['conditions'] as $cond ) {
				if ( ! self::matches( $cond, $fields ) ) {
					return false;
				}
			}
			return true;
		}

		$field = isset( $rule['field'] ) ? $rule['field'] : '';
		if ( '' === $field ) {
			// A rule with no condition always matches (e.g. a flat base price).
			return true;
		}

		$actual   = isset( $fields[ $field ] ) ? $fields[ $field ] : '';
		$operator = isset( $rule['operator'] ) ? $rule['operator'] : 'equals';
		$expected = isset( $rule['value'] ) ? $rule['value'] : '';

		if ( is_array( $actual ) ) {
			// For multi-value checkbox fields, "contains"/"equals" test membership.
			switch ( $operator ) {
				case 'not_equals':
					return ! in_array( (string) $expected, array_map( 'strval', $actual ), true );
				case 'exists':
					return ! empty( $actual );
				default:
					return in_array( (string) $expected, array_map( 'strval', $actual ), true );
			}
		}

		switch ( $operator ) {
			case 'equals':
				return (string) $actual === (string) $expected;
			case 'not_equals':
				return (string) $actual !== (string) $expected;
			case 'contains':
				return '' !== (string) $expected && false !== strpos( (string) $actual, (string) $expected );
			case 'gte':
				return self::to_units( $actual ) >= (float) $expected;
			case 'lte':
				return self::to_units( $actual ) <= (float) $expected;
			case 'exists':
				return '' !== (string) $actual;
			default:
				return false;
		}
	}
}
