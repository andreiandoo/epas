import React from 'react';
import { TouchableOpacity, View, StyleSheet, Animated } from 'react-native';
import { colors, borderRadius } from '../../utils/theme';

interface ToggleProps {
  value: boolean;
  onValueChange: (value: boolean) => void;
  disabled?: boolean;
}

export const Toggle: React.FC<ToggleProps> = ({
  value,
  onValueChange,
  disabled = false,
}) => {
  return (
    <TouchableOpacity
      style={[
        styles.container,
        value ? styles.containerActive : styles.containerInactive,
        disabled && styles.disabled,
      ]}
      onPress={() => !disabled && onValueChange(!value)}
      activeOpacity={0.8}
    >
      <View
        style={[
          styles.thumb,
          value ? styles.thumbActive : styles.thumbInactive,
        ]}
      />
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  container: {
    width: 48,
    height: 28,
    borderRadius: 14,
    padding: 2,
    justifyContent: 'center',
  },
  containerActive: {
    backgroundColor: colors.primary,
  },
  containerInactive: {
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
  },
  disabled: {
    opacity: 0.5,
  },
  thumb: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: colors.textPrimary,
  },
  thumbActive: {
    alignSelf: 'flex-end',
  },
  thumbInactive: {
    alignSelf: 'flex-start',
  },
});

export default Toggle;
