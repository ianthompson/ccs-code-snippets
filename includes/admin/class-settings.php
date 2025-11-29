<?php
/**
 * Settings Manager
 *
 * Handles plugin settings including GitHub updater configuration.
 *
 * @package CCS_Code_Snippets
 * @since 0.1.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CCS_Settings
 *
 * Manages plugin settings and options.
 */
class CCS_Settings {

    /**
     * Option name for settings.
     *
     * @var string
     */
    const OPTION_NAME = 'ccs_settings';

    /**
     * Initialize settings.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Register plugin settings.
     *
     * @since 0.1.3
     */
    public function register_settings() {
        register_setting(
            'ccs_settings_group',
            self::OPTION_NAME,
            [ $this, 'sanitize_settings' ]
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @since 0.1.3
     * @param array $input Raw input data
     * @return array Sanitized data
     */
    public function sanitize_settings( $input ) {
        $sanitized = [];

        if ( isset( $input['github_user'] ) ) {
            $sanitized['github_user'] = sanitize_text_field( $input['github_user'] );
        }

        if ( isset( $input['github_repo'] ) ) {
            $sanitized['github_repo'] = sanitize_text_field( $input['github_repo'] );
        }

        if ( isset( $input['github_token'] ) ) {
            // Only update token if a new value is provided
            $token = trim( $input['github_token'] );
            if ( ! empty( $token ) ) {
                $sanitized['github_token'] = sanitize_text_field( $token );
            } else {
                // Keep existing token if field is empty
                $existing = $this->get_settings();
                $sanitized['github_token'] = $existing['github_token'] ?? '';
            }
        }

        // Clear GitHub update cache when settings change
        delete_transient( 'ccs_gh_release_' . ( $sanitized['github_repo'] ?? 'ccs-code-snippets' ) );

        return $sanitized;
    }

    /**
     * Get all settings.
     *
     * @since 0.1.3
     * @return array Settings array
     */
    public static function get_settings() {
        $defaults = [
            'github_user'  => CCS_GITHUB_USER,
            'github_repo'  => CCS_GITHUB_REPO,
            'github_token' => CCS_ACCESS_TOKEN,
        ];

        return wp_parse_args( get_option( self::OPTION_NAME, [] ), $defaults );
    }

    /**
     * Get a specific setting.
     *
     * @since 0.1.3
     * @param string $key     Setting key
     * @param mixed  $default Default value
     * @return mixed Setting value
     */
    public static function get( $key, $default = '' ) {
        $settings = self::get_settings();
        return $settings[ $key ] ?? $default;
    }

    /**
     * Render GitHub settings section.
     *
     * @since 0.1.3
     */
    public static function render_github_settings() {
        $settings = self::get_settings();
        ?>
        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; min-width:300px; max-width:600px;">
            <h2><?php esc_html_e( 'GitHub Auto-Update Settings', 'ccs-snippets' ); ?></h2>
            <p><?php esc_html_e( 'Configure where the plugin checks for updates.', 'ccs-snippets' ); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'ccs_settings_group' );
                ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="ccs_github_user">
                                    <?php esc_html_e( 'GitHub Username', 'ccs-snippets' ); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="ccs_github_user"
                                    name="<?php echo esc_attr( self::OPTION_NAME ); ?>[github_user]"
                                    value="<?php echo esc_attr( $settings['github_user'] ); ?>"
                                    class="regular-text"
                                    placeholder="ianthompson"
                                >
                                <p class="description">
                                    <?php esc_html_e( 'The GitHub username or organization name.', 'ccs-snippets' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ccs_github_repo">
                                    <?php esc_html_e( 'Repository Name', 'ccs-snippets' ); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="ccs_github_repo"
                                    name="<?php echo esc_attr( self::OPTION_NAME ); ?>[github_repo]"
                                    value="<?php echo esc_attr( $settings['github_repo'] ); ?>"
                                    class="regular-text"
                                    placeholder="ccs-code-snippets"
                                >
                                <p class="description">
                                    <?php esc_html_e( 'The GitHub repository name.', 'ccs-snippets' ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ccs_github_token">
                                    <?php esc_html_e( 'Access Token (Optional)', 'ccs-snippets' ); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="password"
                                    id="ccs_github_token"
                                    name="<?php echo esc_attr( self::OPTION_NAME ); ?>[github_token]"
                                    value="<?php echo esc_attr( $settings['github_token'] ); ?>"
                                    class="regular-text"
                                    placeholder="<?php echo ! empty( $settings['github_token'] ) ? '••••••••••••••••' : esc_attr__( 'Leave empty for public repos', 'ccs-snippets' ); ?>"
                                    autocomplete="off"
                                >
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: GitHub settings URL */
                                        esc_html__( 'Required only for private repositories. Generate a token at %s', 'ccs-snippets' ),
                                        '<a href="https://github.com/settings/tokens" target="_blank">GitHub Settings</a>'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Save GitHub Settings', 'ccs-snippets' ) ); ?>
            </form>

            <hr style="margin: 20px 0;">

            <h3><?php esc_html_e( 'Current Configuration', 'ccs-snippets' ); ?></h3>
            <p>
                <strong><?php esc_html_e( 'Repository:', 'ccs-snippets' ); ?></strong>
                <code><?php echo esc_html( $settings['github_user'] . '/' . $settings['github_repo'] ); ?></code>
            </p>
            <p>
                <strong><?php esc_html_e( 'Token Status:', 'ccs-snippets' ); ?></strong>
                <?php
                if ( ! empty( $settings['github_token'] ) ) {
                    echo '<span style="color: green;">✓ ' . esc_html__( 'Configured', 'ccs-snippets' ) . '</span>';
                } else {
                    echo '<span style="color: #999;">○ ' . esc_html__( 'Not configured (public repo mode)', 'ccs-snippets' ) . '</span>';
                }
                ?>
            </p>
        </div>
        <?php
    }
}
