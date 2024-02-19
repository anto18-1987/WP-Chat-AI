<?php
/*
Plugin Name: WP Chat AI
Description: Chat with your website visitors powered by ChatGPT.
Version: 1.0
Author: Anto Mathew
*/
require __DIR__ . '/vendor/autoload.php'; 

use Orhanerday\OpenAi\OpenAi;

class WP_Chat_AI {
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'plugin_activation'));
        register_deactivation_hook(__FILE__, array($this, 'plugin_deactivation'));
        add_action('<wp_enqueuprocess_chat_message class="js"></wp_enqueuprocess_chat_message>e_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_process_chat_message', array($this, 'process_chat_message'));
        add_action('wp_ajax_nopriv_process_chat_message',  array($this, 'process_chat_message'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        //add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
    }
    
    public function plugin_activation() {
        // Create a new option in the database to store the chat enabled status
        add_option('chat_enabled', 0);
        
        // Create a new option in the database to store the ChatGPT API key
        add_option('chatgpt_api_key', '');
        add_option('chatgpt_api_model', '');
        
        
        // Flush rewrite rules to ensure custom post types, taxonomies, and permalinks are properly set up
        flush_rewrite_rules();
    }
    public function enqueue_admin_styles() {
        wp_enqueue_style('wp-chat-admin-style', plugins_url('css/wp-chat-admin.css', __FILE__));
    }
    public function enqueue_admin_scripts() {
        wp_enqueue_script('wp-chat-admin-script', plugins_url('js/wp-chat-admin.js', __FILE__), array('jquery'), '1.0', true);
    }
    
    public function register_settings() {
        // Register settings group
        register_setting('wp-chat-ai-settings-group', 'chat_enabled');
        register_setting('wp-chat-ai-settings-group', 'chatgpt_api_key');
        register_setting('wp-chat-ai-settings-group', 'chatgpt_api_model');
        register_setting('wp-chat-ai-settings-group', 'chatgpt_assistant_name');
        

       
    }
    public function settings_section_callback() {
        echo '<p>Configure settings for WP Chat AI plugin.</p>';
    }

    public function render_settings_page_frontend() {
        ?>
        <div class="wrap">
            <h2>WP Chat AI Settings</h2>

            <!-- Chat popup container -->
            <div id="chat-popup">
                <div class="chat-popup-content">
                    <span class="close">&times;</span>
                    <h3>Chat with Us</h3>
                    <form id="chat-form">
                        <input type="text" name="message" placeholder="Enter your message..." />
                        <button type="submit">Send</button>
                    </form>
                </div>
            </div>

            <!-- Your existing settings form goes here -->
        </div>
        <?php
    }

    public function plugin_deactivation() {
        // Remove the chat enabled option from the database
        delete_option('chat_enabled');

        // Remove the ChatGPT API key option from the database
        delete_option('chatgpt_api_key');

        delete_option('chatgpt_api_model');
        delete_option('chatgpt_assistant_name');
        
        // Flush rewrite rules to ensure proper cleanup
        flush_rewrite_rules();
    }
    

    public function add_admin_menu() {
        add_options_page('WP Chat AI Settings', 'WP Chat AI', 'manage_options', 'wp-chat-ai-settings', array($this, 'render_settings_page'));
    }

    public function render_settings_page() {
        ?>
        <div class="wrap wp-chat-admin-wrap">
            <h2>WP Chat AI Settings</h2>
            
            <form method="post" action="options.php">
                <?php settings_fields('wp-chat-ai-settings-group'); ?>
                <?php do_settings_sections('wp-chat-ai-settings-group'); ?>
                <label for="chat_enabled">Enable Chat:<?php
                $chat_enabled = get_option('chat_enabled');
            ?>
            </label>
                <label class="toggle-label">
                    <input type="checkbox" id="chat_enabled" name="chat_enabled" <?php if ($chat_enabled == 'on') echo "checked"; ?> />
                    <div class="toggle <?php if ($chat_enabled == 'on') echo "active"; ?>"></div>
                </label>
                <label for="chatgpt_api_key">ChatGPT API Key:</label>
                <input type="text" id="chatgpt_api_key" name="chatgpt_api_key" value="<?php echo esc_attr(get_option('chatgpt_api_key')); ?>" />
                <label for="chatgpt_api_key">ChatGPT API Model:</label>
                <input type="text" id="chatgpt_api_model" name="chatgpt_api_model" value="<?php echo esc_attr(get_option('chatgpt_api_model')); ?>" />
                <label for="chatgpt_api_key">ChatGPT Assistan Name:</label>
                <input type="text" id="chatgpt_assistant_name" name="chatgpt_assistant_name" value="<?php echo esc_attr(get_option('chatgpt_assistant_name')); ?>" />
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }    
    public function localize_ajaxurl() {
        wp_localize_script('chat-popup-script', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
    }

    public function process_chat_message() {
        $message = isset($_POST['message']) ? $_POST['message'] : '';
        //echo $message;exit;
        // Process message with ChatGPT API using cURL
        $chatgpt_api_key = get_option('chatgpt_api_key');
        
        $response = $this->send_message_to_chatgpt($message, $chatgpt_api_key);
        echo json_encode($response);
        wp_die();
    }
    public function enqueue_scripts() {
        // Enqueue script for chat popup if chat is enabled
        if (get_option('chat_enabled')) {
            wp_enqueue_style('wp-chat-popup-style', plugins_url('css/wp-chat-popup.css', __FILE__));
            wp_enqueue_script('chat-popup-script', plugins_url('js/wp-chat-popup.js', __FILE__), array('jquery'), '1.0', true);
            $this->localize_ajaxurl();
       }
    }
   
    private function custom_word_search($search_term) {
        // Split the sentence into individual words
        $words = explode(' ', $search_term);
        $content='';
        // Loop through each word
        foreach ($words as $word) {
            // Trim the word to remove any leading or trailing spaces
            $word = trim($word);

            // Perform the search for the word
            $args = array(
                'post_type'      => 'post', // Adjust post type if needed
                'post_status'    => 'publish',
                's'              => $word, // Search term
            );

            // The Query
            $query = new WP_Query( $args );

            // Check if any posts were found
            if ( $query->have_posts() ) {
                // Start the loop
                while ( $query->have_posts() ) {
                    $query->the_post();
                    // Display post title and content
                    $content.=get_the_content();
                    $content.'<br/>For more details <a href="' . get_permalink() . '">' . get_the_title() . '</a>';
                    //break;
                }
            }
            // Restore original post data
            wp_reset_postdata();
        }
        $text_without_html = strip_tags($content);

        // Tokenize the text
        $tokens = preg_split('/\s+/', $text_without_html);
    
        // Limit to 4000 tokens
        $limited_tokens = array_slice($tokens, 0, 4000);
    
        // Join tokens back into text
        $limited_text = implode(' ', $limited_tokens);
        //echo $limited_text;
        return $limited_text;
    }
    
    private function send_message_to_chatgpt($message, $open_ai_key) {
        
        $open_ai = new OpenAi($open_ai_key);
        $chatgpt_api_model = get_option('chatgpt_api_model');
        $chatgpt_assistant_name = get_option('chatgpt_assistant_name');
        
        
        // Your code to send message to ChatGPT API using cURL
         $content=$this->custom_word_search($message);
       // Long content (your knowledge base)
        //$prompt = `You are an assistant named John, answering questions about 'Website Contnet ,introduce yourself to mesaage when start'  , The  content is :`.$content.` and question is `.$message;
        $prompt = "You are an assistant named ".$chatgpt_assistant_name.", answering questions about ".get_bloginfo('name').". Introduce yourself to the message when you start. The content is: $content and the question is: $message";
        // User's message
        //$message = "Can you tell me more about ipsum?";

        // Construct prompt based on user's message and the preprocessed content
        
       
        $complete = $open_ai->chat([
            'model' => $chatgpt_api_model,
            'messages' => [
                [
                    "role" => "assistant",
                    "content" => $prompt
                ],
            ],
            'temperature' => 1.0,
            'max_tokens' => 4000,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ]);
        
        $response = json_decode($complete, true);
       
        if (isset($response['choices'])) {
            // Output the completion
            return $response['choices'][0]['message']['content'];
        } else {
            // Output the error message
            return $response['error']['message'];
        }

        
    }    
}

$wp_chat_ai = new WP_Chat_AI();
