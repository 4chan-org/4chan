require 'rake/testtask'
require 'uglifier'
require 'openssl'
require 'json'
require 'open3'
require 'fileutils'

Encoding.default_external = 'UTF-8'

include Rake

def minify_js(basename)
  root = 'js'
  
  u = Uglifier.new(
    :harmony => true
    #screw_ie8: true,
    #source_map: {
    #  source_filename: "#{basename}#{version}.js",
    #  output_filename: "#{basename}.min#{version}.js"
    #}
  )
  
  js, sm = u.compile_with_map(File.read("#{root}/#{basename}.js"))
  
  #hash = OpenSSL::Digest::MD5.hexdigest(sm)[0,8]
  #js << "\n//# sourceMappingURL=#{basename}.min.map?#{hash}"
  
  out = if (basename =~ /-unminified$/)
    "#{root}/#{basename.sub(/-.+$/, '')}.js"
  else
    "#{root}/#{basename}.min.js"
  end
  
  File.open(out, 'w') { |f| f.write js }
  #File.open("#{root}/#{basename}.min.map", 'w') { |f| f.write sm }
end

desc 'Minify JavaScript and generate source maps'
task :minify, [:js] do |t, args|
  puts "Compiling #{args[:js]}.js"
  minify_js(args[:js])
end

task :jshint, [:js] do |t, args|
  root = 'js'
  
  basename = args[:js]
  
  if !basename
    abort 'File not found.'
  end
  
  file = "#{root}/#{basename}.js"
  
  if !File.exist?(file)
    abort 'File not found.'
  end
  
  opts = {
    laxbreak: true,
    esversion: 6,
    boss: true,
    expr: true,
    sub: true,
    browser: true,
    devel: true,
    strict: 'implied',
    multistr: true,
    scripturl: true,
    unused: 'vars',
    evil: true,
    '-W079' => true # no-native-reassign
  }

  opts[:globals] = Hash[[
    '$', '$L', 'Chart', 'Feedback', 'Tip', 'APP', 'Tegaki', 'MathJax', 'Main', 'UA',
    'Draggable', 'Config', 'Parser', 'ThreadUpdater', 'SettingsMenu', 'QR', 'FC',
    'grecaptcha', 'Recaptcha', 'ados_refresh', 'style_group', 'StickyNav',
    'PostMenu', 'StorageSync', 'OGVPlayer', 'TCaptcha'
  ].collect { |v| [v, false] }]
  
  cfg_path = 'tmp_jshint.json'
  
  File.write(cfg_path, opts.to_json)
  
  puts "--> #{file}"
  output, outerr, status = Open3.capture3('jshint', file, '--config', cfg_path)
  puts output
  
  FileUtils.rm(cfg_path)
end

namespace :concat do
  desc 'Concatenate painter.js files'
  task :painter do
    puts 'Building painter.js'
    
    root = 'js'
    out_file = "#{root}/painter.js"
    js = []
    
    ['tegaki.js', 'painter-strings.js'].each do |file|
      js << File.binread("#{root}/#{file}")
    end
    
    File.binwrite(out_file, js.join("\n"))
  end
end
