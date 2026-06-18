import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'features/auth/auth_controller.dart';
import 'features/auth/auth_state.dart';
import 'features/auth/login_screen.dart';
import 'features/home/home_screen.dart';
import 'features/notifications/notification_controller.dart';
import 'features/presence/heartbeat_controller.dart';
import 'theme/app_theme.dart';

void main() {
  runApp(const ProviderScope(child: SmartQueueApp()));
}

class SmartQueueApp extends StatelessWidget {
  const SmartQueueApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Smart Queue',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: ThemeMode.system,
      home: const AuthGate(),
    );
  }
}

/// Restores the session on launch, then routes by auth status:
///   - [AuthStatus.unknown]         → splash while `bootstrap()` runs
///   - [AuthStatus.unauthenticated] → [LoginScreen]
///   - [AuthStatus.authenticated]   → [HomeScreen]
class AuthGate extends ConsumerStatefulWidget {
  const AuthGate({super.key});

  @override
  ConsumerState<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends ConsumerState<AuthGate> {
  @override
  void initState() {
    super.initState();
    // Defer to the next frame so the provider container is fully ready.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(authControllerProvider.notifier).bootstrap();
    });
  }

  @override
  Widget build(BuildContext context) {
    final status = ref.watch(
      authControllerProvider.select((s) => s.status),
    );

    final Widget child = switch (status) {
      AuthStatus.unknown => const _SplashScreen(),
      AuthStatus.unauthenticated => const LoginScreen(),
      AuthStatus.authenticated => const _AuthenticatedRoot(),
    };

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 280),
      switchInCurve: Curves.easeOut,
      switchOutCurve: Curves.easeIn,
      child: KeyedSubtree(key: ValueKey(status), child: child),
    );
  }
}

/// Authenticated landing. Keeps the presence/heartbeat controller alive for the
/// whole signed-in session so it can start the loop the moment a ticket becomes
/// active and tear it down on leave/served/logout — independent of which screen
/// is currently on top. The controller itself stays idle (no timer, no network)
/// until [hasActiveTicketProvider] flips true, so this is battery-friendly.
class _AuthenticatedRoot extends ConsumerWidget {
  const _AuthenticatedRoot();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.watch(presenceControllerProvider);
    // Keep the notification controller alive for the whole session so queue
    // events (and any FCM pushes) surface as OS notifications regardless of the
    // current screen (task 033).
    ref.watch(notificationControllerProvider);
    return const HomeScreen();
  }
}

/// Branded splash shown while the stored token is validated against `/api/me`.
class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Hero(
              tag: 'app-glyph',
              child: Container(
                height: 72,
                width: 72,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [scheme.primary, scheme.tertiary],
                  ),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Icon(Icons.confirmation_number_outlined,
                    color: scheme.onPrimary, size: 36),
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            const SizedBox(
              height: 24,
              width: 24,
              child: CircularProgressIndicator(strokeWidth: 2.4),
            ),
          ],
        ),
      ),
    );
  }
}
